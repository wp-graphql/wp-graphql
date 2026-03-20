<?php
/**
 * GET request handler for cache miss path
 *
 * @package WPGraphQL\PQC\Request
 * @since 0.1.0-beta.1
 */

namespace WPGraphQL\PQC\Request;

use WPGraphQL\PQC\Store\StoreFactory;
use WPGraphQL\PQC\Utils\Hasher;

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
		// Look up query from store.
		$store = StoreFactory::get_store();
		$query_data = $store->get_query( $query_hash, $variables_hash ?: '' );

		if ( ! $query_data ) {
			// Query not found in index, return 404.
			status_header( 404 );
			nocache_headers();
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

		// Re-execute the query.
		$this->execute_query( $query_data['query_document'], $variables );
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
		header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );

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
