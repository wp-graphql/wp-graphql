<?php
/**
 * Bootstrap file for functional tests.
 *
 * @package WPGraphQL\Tests\Functional
 */

/**
 * Helper constant used by the tests to query the GraphQL endpoint.
 */
if ( ! defined( 'TEST_GRAPHQL_ENDPOINT' ) ) {
	$graphql_base = getenv( 'TEST_WP_URL' );
	define( 'TEST_GRAPHQL_ENDPOINT', rtrim( $graphql_base, '/' ) . '/graphql' );
}
