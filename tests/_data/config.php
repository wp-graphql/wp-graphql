<?php
/**
 * Disable autoloading while running tests, as the test
 * suite already bootstraps the autoloader and creates
 * fatal errors when the autoloader is loaded twice
 */
define( 'GRAPHQL_DEBUG', true );
define( 'WPGRAPHQL_AUTOLOAD', false );
define( 'WP_AUTO_UPDATE_CORE', false );
add_filter( 'enable_maintenance_mode', '__return_false' );
add_filter( 'wp_auto_update_core', '__return_false' );
add_filter( 'auto_update_plugin', '__return_false' );
add_filter( 'auto_update_theme', '__return_false' );
