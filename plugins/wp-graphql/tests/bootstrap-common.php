<?php
/**
 * Common bootstrap file for all WPGraphQL monorepo tests.
 *
 * This file contains shared bootstrap code used across all plugins in the monorepo.
 * Individual plugin bootstrap files should require this file.
 *
 * @package WPGraphQL\Tests
 */

/**
 * Helper constant used by the tests to query the GraphQL endpoint.
 */
if ( ! defined( 'TEST_GRAPHQL_ENDPOINT' ) ) {
	$graphql_base = getenv( 'TEST_WP_URL' );
	define( 'TEST_GRAPHQL_ENDPOINT', rtrim( $graphql_base, '/' ) . '/graphql' );
}
