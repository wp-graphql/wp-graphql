<?php
/**
 * Router for handling persisted query URLs
 *
 * @package WPGraphQL\PQC
 * @since 0.1.0-beta.1
 */

namespace WPGraphQL\PQC;

/**
 * Class Router
 *
 * @package WPGraphQL\PQC
 */
class Router {

	/**
	 * Initialize the router
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', [ $this, 'add_rewrite_rules' ], 10 );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ], 10, 1 );
		add_action( 'parse_request', [ $this, 'handle_persisted_query_request' ], 10 );
	}

	/**
	 * Add rewrite rules for persisted query URLs
	 *
	 * @return void
	 */
	public function add_rewrite_rules(): void {
		$base_path = $this->get_base_path();

		// Pattern: /graphql/persisted/{queryHash}
		add_rewrite_rule(
			$base_path . '([a-f0-9]{64})/?$',
			'index.php?graphql_persisted_query=1&graphql_query_hash=$matches[1]',
			'top'
		);

		// Pattern: /graphql/persisted/{queryHash}/variables/{variablesHash}
		add_rewrite_rule(
			$base_path . '([a-f0-9]{64})/variables/([a-f0-9]{64})/?$',
			'index.php?graphql_persisted_query=1&graphql_query_hash=$matches[1]&graphql_variables_hash=$matches[2]',
			'top'
		);
	}

	/**
	 * Add custom query vars
	 *
	 * @param array $vars Existing query vars.
	 * @return array
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'graphql_persisted_query';
		$vars[] = 'graphql_query_hash';
		$vars[] = 'graphql_variables_hash';

		return $vars;
	}

	/**
	 * Handle persisted query GET requests
	 *
	 * @param \WP $wp The WordPress environment class.
	 * @return void
	 */
	public function handle_persisted_query_request( $wp ): void {
		// Check if this is a persisted query request.
		$is_persisted_query = isset( $wp->query_vars['graphql_persisted_query'] ) && $wp->query_vars['graphql_persisted_query'];

		if ( ! $is_persisted_query ) {
			return;
		}

		// Only handle GET requests.
		if ( 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		// Persisted query URLs should always return JSON, not a PHP template.
		// We'll handle authentication context in the GET handler to ensure public data only.

		$query_hash = isset( $wp->query_vars['graphql_query_hash'] ) ? $wp->query_vars['graphql_query_hash'] : '';
		$variables_hash = isset( $wp->query_vars['graphql_variables_hash'] ) ? $wp->query_vars['graphql_variables_hash'] : '';

		if ( ! $query_hash || ! preg_match( '/^[a-f0-9]{64}$/', $query_hash ) ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}

		// If variables_hash is provided, validate it.
		if ( ! empty( $variables_hash ) && ! preg_match( '/^[a-f0-9]{64}$/', $variables_hash ) ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}

		// Normalize variables_hash: use empty string if not provided.
		$variables_hash = $variables_hash ?: '';

		// Delegate to GET handler.
		$get_handler = new Request\GetHandler();
		$get_handler->handle( $query_hash, $variables_hash );
	}

	/**
	 * Get the base path for persisted query URLs
	 *
	 * @return string
	 */
	private function get_base_path(): string {
		/**
		 * Filter the base path for persisted query URLs
		 *
		 * @param string $base_path The base path (default: 'graphql/persisted/').
		 * @return string
		 */
		$base_path = apply_filters( 'wpgraphql_pqc_url_base', 'graphql/persisted/' );

		// Ensure it ends with a slash.
		if ( ! empty( $base_path ) && '/' !== substr( $base_path, -1 ) ) {
			$base_path .= '/';
		}

		return $base_path;
	}
}
