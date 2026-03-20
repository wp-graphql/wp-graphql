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

		// Check if we should store this request (only unauthenticated, unless filtered).
		/**
		 * Filter whether to allow authenticated requests to be stored.
		 * Default is false (only unauthenticated requests are stored).
		 *
		 * @param bool $allow_authenticated Whether to allow authenticated requests.
		 * @return bool
		 */
		$allow_authenticated = apply_filters( 'wpgraphql_pqc_allow_authenticated', false );
		$should_store = ! is_user_logged_in() || $allow_authenticated;

		// Store in index (only for unauthenticated requests, unless filtered).
		if ( $should_store ) {
			$store = StoreFactory::get_store();
			$store->store( $url, $query_hash, $variables_hash ?: '', $query, $variables_json, $cache_keys );
		}

		// Always add canonical URL to response extensions (for testing/debugging).
		$this->add_url_to_extensions( $filtered_response, $url );

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
