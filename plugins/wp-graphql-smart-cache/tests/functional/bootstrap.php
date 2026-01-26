<?php
/**
 * Bootstrap file for functional tests.
 *
 * @package WPGraphQL\SmartCache\Tests\Functional
 */

/**
 * Helper constant used by the tests to query the GraphQL endpoint.
 */
if ( ! defined( 'TEST_GRAPHQL_ENDPOINT' ) ) {
	$graphql_base = getenv( 'TEST_WP_URL' );
	define( 'TEST_GRAPHQL_ENDPOINT', rtrim( $graphql_base, '/' ) . '/graphql' );
}

/**
 * Ensure wp-graphql-smart-cache is activated for these tests.
 * This is done here rather than in the setup script to avoid affecting wp-graphql-only tests.
 * 
 * Note: This runs after WordPress is loaded by WPLoader (with loadOnly: true),
 * so we can use WordPress functions to activate the plugin.
 */
add_action( 'init', function() {
	$plugin_file = 'wp-graphql-smart-cache/wp-graphql-smart-cache.php';
	if ( ! is_plugin_active( $plugin_file ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		activate_plugin( $plugin_file );
		// Flush rewrite rules after activation so GraphQL endpoint is registered
		flush_rewrite_rules( false );
	}
}, 1 );
