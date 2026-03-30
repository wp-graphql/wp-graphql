<?php
/**
 * GET request handler for cache miss path
 *
 * @package WPGraphQL\PQC\Request
 * @since 0.1.0-beta.1
 */

namespace WPGraphQL\PQC\Request;

use GraphQL\Language\Parser;
use WPGraphQL\PQC\Store\StoreFactory;
use WPGraphQL\PQC\Utils\Nonce;

/**
 * Class GetHandler
 *
 * @package WPGraphQL\PQC\Request
 */
class GetHandler {

	/**
	 * Handle GET request for persisted query
	 *
	 * @param string      $query_hash    The query hash.
	 * @param string|null $variables_hash The variables hash, or empty string if no variables.
	 * @return void
	 */
	public function handle( string $query_hash, ?string $variables_hash ): void {
		try {
			$this->do_handle( $query_hash, $variables_hash );
		} catch ( \Throwable $e ) {
			// Avoid WordPress generic "critical error" screen; return JSON for API clients.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[WPGraphQL PQC] GetHandler: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );

			if ( ! headers_sent() ) {
				status_header( 500 );
				header( 'Cache-Control: no-store' );
				$charset = get_option( 'blog_charset' );
				header( 'Content-Type: application/json; charset=' . ( is_string( $charset ) && '' !== $charset ? $charset : 'UTF-8' ) );
			}

			$message = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? $e->getMessage() : __( 'Persisted query request failed.', 'wp-graphql-pqc' );

			$response = [
				'errors' => [
					[
						'message' => $message,
						'extensions' => [
							'code' => 'PQC_INTERNAL_ERROR',
						],
					],
				],
			];

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo wp_json_encode( $response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			exit;
		}
	}

	/**
	 * Perform persisted GET handling (wrapped by handle() for Throwable safety).
	 *
	 * @param string      $query_hash    The query hash.
	 * @param string|null $variables_hash The variables hash, or empty string if none.
	 * @return void
	 */
	private function do_handle( string $query_hash, ?string $variables_hash ): void {
		// Look up query from store.
		$store = StoreFactory::get_store();
		$query_data = $store->get_query( $query_hash, $variables_hash ?: '' );

		if ( ! $query_data ) {
			// Query not found in index, return GraphQL error response with nonce in extensions.
			// Use HTTP 200 (GraphQL convention: errors are in response, not HTTP status).
			$nonce = Nonce::generate( $query_hash, $variables_hash );

			// Prevent WordPress from loading a template.
			status_header( 200 );

			// Set no-cache headers for error responses (nonce should not be cached).
			header( 'Cache-Control: no-store' );
			$charset = get_option( 'blog_charset' );
			header( 'Content-Type: application/json; charset=' . ( is_string( $charset ) && '' !== $charset ? $charset : 'UTF-8' ) );

			$response = [
				'errors' => [
					[
						'message' => 'Persisted query not found',
						'extensions' => [
							'code' => 'PERSISTED_QUERY_NOT_FOUND',
						],
					],
				],
				'extensions' => [
					'persistedQueryNonce' => $nonce,
				],
			];

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo wp_json_encode( $response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			exit;
		}

		// Parse variables if provided.
		$variables = null;
		if ( ! empty( $query_data['variables'] ) ) {
			$decoded = json_decode( $query_data['variables'], true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$variables = $decoded;
			}
		}

		// Reject corrupt index rows (invalid GraphQL) with a clear response instead of a fatal.
		$query_document = $query_data['query_document'];
		if ( ! is_string( $query_document ) || '' === $query_document ) {
			$this->send_document_invalid_response( __( 'Stored persisted query document is missing. Register the query again with a POST to /graphql.', 'wp-graphql-pqc' ) );
		}

		try {
			Parser::parse( $query_document );
		} catch ( \Throwable $e ) {
			$detail = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? $e->getMessage() : '';
			$message = __( 'Stored persisted query document is invalid. Register the query again with a POST to /graphql.', 'wp-graphql-pqc' );
			if ( '' !== $detail ) {
				$message .= ' ' . $detail;
			}
			$this->send_document_invalid_response( $message );
		}

		// Re-execute the query.
		$this->execute_query( $query_document, $variables );
	}

	/**
	 * JSON response for invalid stored documents (HTTP 200, GraphQL-style errors).
	 *
	 * @param string $message User-facing message.
	 * @return void
	 */
	private function send_document_invalid_response( string $message ): void {
		status_header( 200 );
		header( 'Cache-Control: no-store' );
		$charset = get_option( 'blog_charset' );
		header( 'Content-Type: application/json; charset=' . ( is_string( $charset ) && '' !== $charset ? $charset : 'UTF-8' ) );

		$response = [
			'errors' => [
				[
					'message'    => $message,
					'extensions' => [
						'code' => 'PERSISTED_QUERY_DOCUMENT_INVALID',
					],
				],
			],
		];

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wp_json_encode( $response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * Execute a GraphQL query
	 *
	 * @param string     $query     The query document.
	 * @param array|null $variables The variables.
	 * @return void
	 */
	private function execute_query( string $query, ?array $variables ): void {
		// Check if the request is authenticated (for cache header determination).
		$is_authenticated = is_user_logged_in();

		// Set up GraphQL request.
		$request_data = [
			'query' => $query,
		];

		if ( ! empty( $variables ) ) {
			$request_data['variables'] = $variables;
		}

		// Define a constant to indicate this is an internal call from GET handler.
		// This allows the POST handler to also store index entries for re-executed queries.
		if ( ! defined( 'WPGRAPHQL_PQC_INTERNAL_CALL' ) ) {
			define( 'WPGRAPHQL_PQC_INTERNAL_CALL', true );
		}

		// Execute the query using WPGraphQL's graphql() function.
		// This will execute with the current user context (authenticated or public).
		// Authenticated users will see their own data; public users will see public data.
		$response = graphql( $request_data );

		// Set appropriate headers.
		status_header( 200 );

		// Set content type.
		$charset = get_option( 'blog_charset' );
		header( 'Content-Type: application/json; charset=' . ( is_string( $charset ) && '' !== $charset ? $charset : 'UTF-8' ) );

		// Set cache headers based on authentication status.
		if ( $is_authenticated ) {
			// Authenticated requests: set no-store to prevent caching of user-specific data.
			header( 'Cache-Control: no-store' );
		} else {
			// Public requests: set cacheable headers following Smart Cache settings.
			$this->set_cache_headers();
		}

		// Output JSON response.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wp_json_encode( $response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		exit;
	}

	/**
	 * Set cache headers for persisted query responses
	 *
	 * Persisted queries always return public data, so they should be cacheable.
	 * We respect WPGraphQL Smart Cache's max-age settings if available.
	 *
	 * @return void
	 */
	private function set_cache_headers(): void {
		$max_age = null;

		// Check if Smart Cache is active and has global max-age settings.
		if ( function_exists( 'get_graphql_setting' ) ) {
			$max_age = get_graphql_setting( 'global_max_age', null, 'graphql_cache_section' );
		}

		// If Smart Cache max-age is not set, use a filter to allow customization.
		if ( null === $max_age ) {
			/**
			 * Filter the max-age for persisted query cache headers.
			 *
			 * @param int|null $max_age Max age in seconds (default: 600 = 10 minutes).
			 * @return int|null
			 */
			$max_age = apply_filters( 'wpgraphql_pqc_cache_max_age', 600 );
		}

		// Set cache headers based on max-age value.
		if ( null !== $max_age && $max_age > 0 ) {
			// Positive max-age: allow caching.
			header( sprintf( 'Cache-Control: max-age=%d, s-maxage=%d, must-revalidate', intval( $max_age ), intval( $max_age ) ) );
		} elseif ( 0 === $max_age ) {
			// Zero max-age: no-store (don't cache).
			header( 'Cache-Control: no-store' );
		} else {
			// Negative or null: default to allowing caching with reasonable TTL.
			header( 'Cache-Control: max-age=600, s-maxage=600, must-revalidate' );
		}
	}
}
