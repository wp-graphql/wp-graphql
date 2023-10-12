<?php
/**
 * Plugin Name: WPGraphQL
 * Plugin URI: https://github.com/wp-graphql/wp-graphql
 * GitHub Plugin URI: https://github.com/wp-graphql/wp-graphql
 * Description: GraphQL API for WordPress
 * Author: WPGraphQL
 * Author URI: http://www.wpgraphql.com
 * Version: 1.17.0
 * Text Domain: wp-graphql
 * Domain Path: /languages/
 * Requires at least: 5.0
 * Tested up to: 6.2
 * Requires PHP: 7.1
 * License: GPL-3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package  WPGraphQL
 * @category Core
 * @author   WPGraphQL
 * @version  1.17.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If the codeception remote coverage file exists, require it.
// This file should only exist locally or when CI bootstraps the environment for testing
if ( file_exists( __DIR__ . '/c3.php' ) ) {
	require_once __DIR__ . '/c3.php';
}

/**
 * Load files that are required even if the composer autoloader isn't installed
 *
 * @return void
 */
function graphql_require_bootstrap_files(): void {
	if ( file_exists( __DIR__ . '/constants.php' ) ) {
		require_once __DIR__ . '/constants.php';
	}
	if ( file_exists( __DIR__ . '/activation.php' ) ) {
		require_once __DIR__ . '/activation.php';
	}
	if ( file_exists( __DIR__ . '/deactivation.php' ) ) {
		require_once __DIR__ . '/deactivation.php';
	}
	if ( file_exists( __DIR__ . '/access-functions.php' ) ) {
		require_once __DIR__ . '/access-functions.php';
	}
	if ( file_exists( __DIR__ . '/src/WPGraphQL.php' ) ) {
		require_once __DIR__ . '/src/WPGraphQL.php';
	}
}


/**
 * Determines if the plugin can load.
 *
 * Test env:
 *  - WPGRAPHQL_AUTOLOAD: false
 *  - autoload installed and manually added in test env
 *
 * Bedrock
 *  - WPGRAPHQL_AUTOLOAD: not defined
 *  - composer deps installed outside of the plugin
 *
 * Normal (.org repo install)
 * - WPGRAPHQL_AUTOLOAD: not defined
 * - composer deps installed INSIDE the plugin
 *
 * @return bool
 */
function graphql_can_load_plugin(): bool {

	// Load the bootstrap files (needed before autoloader is configured)
	graphql_require_bootstrap_files();

	// If GraphQL\GraphQL and WPGraphQL are both already loaded,
	// We can assume that WPGraphQL has been installed as a composer dependency of a parent project
	if ( class_exists( 'GraphQL\GraphQL' ) && class_exists( 'WPGraphQL' ) ) {
		return true;
	}

	/**
	 * WPGRAPHQL_AUTOLOAD can be set to "false" to prevent the autoloader from running.
	 * In most cases, this is not something that should be disabled, but some environments
	 * may bootstrap their dependencies in a global autoloader that will autoload files
	 * before we get to this point, and requiring the autoloader again can trigger fatal errors.
	 *
	 * The codeception tests are an example of an environment where adding the autoloader again causes issues
	 * so this is set to false for tests.
	 */
	if ( defined( 'WPGRAPHQL_AUTOLOAD' ) && false === WPGRAPHQL_AUTOLOAD ) {

		// IF WPGRAPHQL_AUTOLOAD is defined as false,
		// but the WPGraphQL Class exists, we can assume the dependencies
		// are loaded from the parent project.
		return true;
	}

	if ( file_exists( plugin_dir_path( __FILE__ ) . 'vendor/autoload.php' ) ) {
		// Autoload Required Classes.
		require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
	}

	// If the GraphQL class still doesn't exist, bail as there was an issue bootstrapping the plugin
	if ( ! class_exists( 'GraphQL\GraphQL' ) || ! class_exists( 'WPGraphQL' ) ) {
		return false;
	}

	return true;
}

if ( ! function_exists( 'graphql_init' ) ) {
	/**
	 * Function that instantiates the plugins main class
	 *
	 * @return object|null
	 */
	function graphql_init() {

		// if the plugin can't be loaded, bail
		if ( false === graphql_can_load_plugin() ) {
			add_action( 'network_admin_notices', 'graphql_cannot_load_admin_notice_callback' );
			add_action( 'admin_notices', 'graphql_cannot_load_admin_notice_callback' );
			return null;
		}

		/**
		 * Return an instance of the action
		 */
		return \WPGraphQL::instance();
	}
}
graphql_init();

// Run this function when WPGraphQL is de-activated
register_deactivation_hook( __FILE__, 'graphql_deactivation_callback' );
register_activation_hook( __FILE__, 'graphql_activation_callback' );

/**
 * Render an admin notice if the plugin cannot load
 *
 * @return void
 */
function graphql_cannot_load_admin_notice_callback(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	printf(
		'<div class="notice notice-error">' .
		'<p>%s</p>' .
		'</div>',
		esc_html__( 'WPGraphQL appears to have been installed without it\'s dependencies. It will not work properly until dependencies are installed. This likely means you have cloned WPGraphQL from Github and need to run the command `composer install`.', 'wp-graphql' )
	);
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once plugin_dir_path( __FILE__ ) . 'cli/wp-cli.php';
}

/**
 * Initialize the plugin tracker
 *
 * @return void
 */
function graphql_init_appsero_telemetry() {
	// If the class doesn't exist, or code is being scanned by PHPSTAN, move on.
	if ( ! class_exists( 'Appsero\Client' ) || defined( 'PHPSTAN' ) ) {
		return;
	}

	$client   = new Appsero\Client( 'cd0d1172-95a0-4460-a36a-2c303807c9ef', 'WPGraphQL', __FILE__ );
	$insights = $client->insights();

	// If the Appsero client has the add_plugin_data method, use it
	if ( method_exists( $insights, 'add_plugin_data' ) ) {
		// @phpstan-ignore-next-line
		$insights->add_plugin_data();
	}

	// @phpstan-ignore-next-line
	$insights->init();
}

graphql_init_appsero_telemetry();
