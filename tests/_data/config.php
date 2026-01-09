<?php
/**
 * Disable autoloading while running tests, as the test
 * suite already bootstraps the autoloader and creates
 * fatal errors when the autoloader is loaded twice
 *
 * @package WPGraphQL/Tests
 */

if ( ! defined( 'AUTOMATIC_UPDATER_DISABLED' ) ) {
	define( 'AUTOMATIC_UPDATER_DISABLED', true );
}
if ( ! defined( 'GRAPHQL_DEBUG' ) ) {
	define( 'GRAPHQL_DEBUG', true );
}
if ( ! defined( 'WP_AUTO_UPDATE_CORE' ) ) {
	define( 'WP_AUTO_UPDATE_CORE', false );
}
if ( ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) ) {
	define( 'WP_HTTP_BLOCK_EXTERNAL', false );
}
if ( ! defined( 'WPGRAPHQL_AUTOLOAD' ) ) {
	define( 'WPGRAPHQL_AUTOLOAD', false );
}
