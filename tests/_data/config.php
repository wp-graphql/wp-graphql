<?php
/**
 * Disable autoloading while running tests, as the test
 * suite already bootstraps the autoloader and creates
 * fatal errors when the autoloader is loaded twice
 *
 * @package WPGraphQL/Tests
 */

define( 'AUTOMATIC_UPDATER_DISABLED', true );
define( 'GRAPHQL_DEBUG', true );
define( 'WP_AUTO_UPDATE_CORE', false );
define( 'WP_HTTP_BLOCK_EXTERNAL', false );
define( 'WPGRAPHQL_AUTOLOAD', false );
