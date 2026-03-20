<?php
/**
 * POST request handler for storing index entries
 *
 * @package WPGraphQL\PQC\Request
 * @since 0.1.0-beta.1
 */

namespace WPGraphQL\PQC\Request;

use WPGraphQL\PQC\Store\StoreFactory;
use WPGraphQL\PQC\Utils\Hasher;
use WPGraphQL\PQC\Utils\Nonce;

/**
 * Class PostHandler
 *
 * @package WPGraphQL\PQC\Request
 */
class PostHandler {

	/**
	 * Initialize the POST handler
	 *
	 * @return void
	 */
	public function init(): void {
		// Use filter to modify response, and action to store data.
		// Priority 15 to run after QueryAnalyzer (10) and Smart Cache (10).
		add_filter( 'graphql_request_results', [ $this, 'handle_post_response' ], 15, 7 );
	}

	/**
	 * Handle POST response to store index entries and add URL to extensions
	 *
	 * @param mixed                $filtered_response The filtered response.
	 * @param \WPGraphQL\WPSchema  $schema           The schema.
	 * @param string|null          $operation        The operation name.
	 * @param string|null          $query            The query document.
	 * @param array|null           $variables        The variables.
	 * @param \WPGraphQL\Request   $request          The request instance.
	 * @param string|null          $query_id         The query ID.
	 * @return mixed The modified response.
	 */
	public function handle_post_response( $filtered_response, $schema, $operation, $query, $variables, $request, $query_id ) {
		// Only handle POST requests or internal graphql() calls from GET handler.
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '';
		$is_internal_call = defined( 'WPGRAPHQL_PQC_INTERNAL_CALL' ) && WPGRAPHQL_PQC_INTERNAL_CALL;

		if ( 'POST' !== $request_method && ! $is_internal_call ) {
			return $filtered_response;
		}

		// Only handle queries (not mutations).
		$query_analyzer = $request->get_query_analyzer();
		$root_operation = $query_analyzer->get_root_operation();
		if ( 'Query' !== $root_operation ) {
			return $filtered_response;
		}

		// Need query document.
		if ( empty( $query ) ) {
			return $filtered_response;
		}

		// Get cache keys from query analyzer.
		$graphql_keys = $query_analyzer->get_graphql_keys();
		$cache_keys = $this->extract_cache_keys( $graphql_keys );

		if ( empty( $cache_keys ) ) {
			return $filtered_response;
		}

		// Compute hashes.
		$query_hash = Hasher::hash_query( $query );
		if ( ! $query_hash ) {
			return $filtered_response;
		}

		$variables_hash = Hasher::hash_variables( $variables );
		$variables_json = ! empty( $variables ) ? wp_json_encode( $variables, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '';

		// Construct canonical URL.
		$url = $this->build_url( $query_hash, $variables_hash );

		// Check if nonce is provided in request extensions (for PQC flow validation).
		$request_extensions = $this->get_request_extensions( $request );
		$nonce = $request_extensions['persistedQueryNonce'] ?? null;
		$client_query_hash = $request_extensions['persistedQueryHash'] ?? null;
		$client_variables_hash = $request_extensions['persistedVariablesHash'] ?? null;

		// If nonce is provided, validate it and the client-provided hashes.
		$nonce_valid = false;
		if ( ! empty( $nonce ) ) {
			// Validate nonce and hash matching.
			$nonce_valid = Nonce::validate( $nonce, $query_hash, $variables_hash );

			// Also validate that client-provided hashes match server-computed hashes.
			if ( $nonce_valid && ! empty( $client_query_hash ) && $client_query_hash !== $query_hash ) {
				$nonce_valid = false;
			}
			if ( $nonce_valid && ! empty( $client_variables_hash ) ) {
				$expected_variables_hash = $variables_hash ?: '';
				if ( $client_variables_hash !== $expected_variables_hash ) {
					$nonce_valid = false;
				}
			}
		}

		$store = StoreFactory::get_store();

		// Check if document already exists.
		$document_exists = $store->document_exists( $query_hash );

		// Determine if we should store the document (if it doesn't exist).
		$should_store_document = false;
		if ( ! $document_exists ) {
			// Document doesn't exist - check permissions for creating it.
			$is_authenticated = is_user_logged_in();

			// Check Smart Cache's grant_mode setting to determine if public document persistence is allowed.
			// If grant_mode is 'public', allow public requests to create documents.
			// Otherwise, only authenticated users can create documents.
			$grant_mode = function_exists( 'get_graphql_setting' ) 
				? \get_graphql_setting( 'grant_mode', 'public', 'graphql_persisted_queries_section' )
				: 'public';
			$allow_public_documents = ( 'public' === $grant_mode );

			/**
			 * Filter whether to require nonce validation for document persistence.
			 * Default is true (nonce required for PQC flow security).
			 * Set to false to allow storage without nonce (e.g., for build tools with Application Passwords).
			 *
			 * @param bool $require_nonce Whether to require nonce validation.
			 * @return bool
			 */
			$require_nonce = apply_filters( 'wpgraphql_pqc_require_nonce', true );

			// Determine if we can store the document:
			// - Must be authenticated OR Smart Cache grant_mode is 'public'
			// - If nonce is required, it must be provided and valid
			$can_store_document = $is_authenticated || $allow_public_documents;
			$nonce_check_passes = $require_nonce ? ( ! empty( $nonce ) && $nonce_valid ) : true;
			$should_store_document = $can_store_document && $nonce_check_passes;
		}

		// Determine if we should store execution data (variables + cache keys).
		// Always allow if document exists, otherwise only if we're storing the document.
		$should_store_execution_data = $document_exists || $should_store_document;

		// Store in index if validation passes.
		if ( $should_store_execution_data ) {
			$store->store( $url, $query_hash, $variables_hash ?: '', $query, $variables_json, $cache_keys, $should_store_document );

			// Mark nonce as used after successful storage.
			if ( ! empty( $nonce ) && $nonce_valid ) {
				Nonce::mark_used( $nonce );
			}

			// Only add canonical URL to response extensions when we actually stored execution data.
			// This prevents returning a URL that will 404.
			$this->add_url_to_extensions( $filtered_response, $url );
		}

		// Return the modified response.
		return $filtered_response;
	}

	/**
	 * Extract cache keys from GraphQL keys array
	 *
	 * @param array $graphql_keys The analyzed keys array.
	 * @return array Array of cache key strings.
	 */
	private function extract_cache_keys( array $graphql_keys ): array {
		if ( empty( $graphql_keys ) ) {
			return [];
		}

		// Get keys string from the analyzed keys array.
		$keys_string = isset( $graphql_keys['keys'] ) ? $graphql_keys['keys'] : '';
		if ( empty( $keys_string ) || ! is_string( $keys_string ) ) {
			return [];
		}

		// Keys are space-separated.
		$keys = array_filter( explode( ' ', $keys_string ) );

		return array_values( $keys );
	}

	/**
	 * Build the canonical persisted query URL
	 *
	 * @param string      $query_hash    The query hash.
	 * @param string|null $variables_hash The variables hash, or null if no variables.
	 * @return string The full URL.
	 */
	private function build_url( string $query_hash, ?string $variables_hash ): string {
		/**
		 * Filter the base path for persisted query URLs
		 *
		 * @param string $base_path The base path (default: 'graphql/persisted/').
		 * @return string
		 */
		$base_path = apply_filters( 'wpgraphql_pqc_url_base', 'graphql/persisted/' );

		// Ensure it doesn't start with a slash (WordPress rewrite rules handle that).
		$base_path = ltrim( $base_path, '/' );

		// Ensure it ends with a slash.
		if ( ! empty( $base_path ) && '/' !== substr( $base_path, -1 ) ) {
			$base_path .= '/';
		}

		$url = $base_path . $query_hash;

		if ( $variables_hash ) {
			$url .= '/variables/' . $variables_hash;
		}

		return '/' . $url;
	}

	/**
	 * Get request extensions from the GraphQL request
	 *
	 * @param \WPGraphQL\Request $request The request object.
	 * @return array Extensions array from the request.
	 */
	private function get_request_extensions( \WPGraphQL\Request $request ): array {
		// Try to get extensions from OperationParams.
		$params = $request->params;
		if ( $params instanceof \GraphQL\Server\OperationParams ) {
			return $params->extensions ?? [];
		}

		// If batch request, get from first operation.
		if ( is_array( $params ) && ! empty( $params[0] ) && $params[0] instanceof \GraphQL\Server\OperationParams ) {
			return $params[0]->extensions ?? [];
		}

		return [];
	}

	/**
	 * Add canonical URL to response extensions
	 *
	 * @param mixed $response The response array or object (passed by reference).
	 * @param string $url The canonical URL.
	 * @return void
	 */
	private function add_url_to_extensions( &$response, string $url ): void {
		if ( ! empty( $response ) ) {
			if ( is_array( $response ) ) {
				if ( ! isset( $response['extensions'] ) ) {
					$response['extensions'] = [];
				}
				$response['extensions']['persistedQueryUrl'] = $url;
			} elseif ( is_object( $response ) ) {
				if ( ! property_exists( $response, 'extensions' ) || ! is_array( $response->extensions ) ) {
					// @phpstan-ignore-next-line
					$response->extensions = [];
				}
				// @phpstan-ignore-next-line
				$response->extensions['persistedQueryUrl'] = $url;
			}
		}
	}
}
