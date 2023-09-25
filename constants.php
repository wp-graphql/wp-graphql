<?php
/**
 * Sets up constants for use throughout the plugin and by other extending plugins.
 *
 * This is in its own file so that it can be used via the autoloaded classes, but also
 * can be pulled in when composer dependencies have not been installed.
 *
 * @return void
 */
function graphql_setup_constants() {

	// Plugin version.
	if ( ! defined( 'WPGRAPHQL_VERSION' ) ) {
		define( 'WPGRAPHQL_VERSION', '1.16.0' );
	}

	// Plugin Folder Path.
	if ( ! defined( 'WPGRAPHQL_PLUGIN_DIR' ) ) {
		define( 'WPGRAPHQL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
	}

	// Plugin Root File.
	if ( ! defined( 'WPGRAPHQL_PLUGIN_FILE' ) ) {
		define( 'WPGRAPHQL_PLUGIN_FILE', WPGRAPHQL_PLUGIN_DIR . '/wp-graphql.php' );
	}

	// The minimum version of PHP this plugin requires to work properly
	if ( ! defined( 'GRAPHQL_MIN_PHP_VERSION' ) ) {
		define( 'GRAPHQL_MIN_PHP_VERSION', '7.1' );
	}
}
