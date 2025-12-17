<?php
/**
 * Bootstrap file for acceptance tests.
 *
 * @package WPGraphQL\Tests\Acceptance
 */

/**
 * Disable updates to prevent WP from going into maintenance mode while tests run.
 */
add_filter( 'enable_maintenance_mode', '__return_false' );
add_filter( 'wp_auto_update_core', '__return_false' );
add_filter( 'auto_update_plugin', '__return_false' );
add_filter( 'auto_update_theme', '__return_false' );

/**
 * Helper constant used by the tests to query the GraphQL sites.
 */
if ( ! defined( 'TEST_GRAPHQL_ENDPOINT' ) ) {
	$graphql_base = getenv( 'TEST_WP_URL' );
	define( 'TEST_GRAPHQL_ENDPOINT', rtrim( $graphql_base, '/' ) . '/graphql' );
}

