<?php
/**
 * Plugin Name: WP GraphQL
 * Plugin URI: https://github.com/wp-graphql/wp-graphql
 * GitHub Plugin URI: https://github.com/wp-graphql/wp-graphql
 * Description: GraphQL API for WordPress
 * Author: WPGraphQL
 * Author URI: http://www.wpgraphql.com
 * Version: 1.6.12
 * Text Domain: wp-graphql
 * Domain Path: /languages/
 * Requires at least: 5.0
 * Tested up to: 5.8
 * Requires PHP: 7.1
 * License: GPL-3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package  WPGraphQL
 * @category Core
 * @author   WPGraphQL
 * @version  1.6.12
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

// Run this function when WPGraphQL is de-activated
register_deactivation_hook( __FILE__, 'graphql_deactivation_callback' );
register_activation_hook( __FILE__, 'graphql_activation_callback' );

// Bootstrap the plugin
if ( ! class_exists( 'WPGraphQL' ) ) {
	require_once __DIR__ . '/src/WPGraphQL.php';
}

if ( ! function_exists( 'graphql_init' ) ) {
	/**
	 * Function that instantiates the plugins main class
	 *
	 * @return object
	 */
	function graphql_init() {
		/**
		 * Return an instance of the action
		 */
		return \WPGraphQL::instance();
	}
}
graphql_init();

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once 'cli/wp-cli.php';
}



add_action( 'admin_menu', function() {

	add_menu_page( 'test', 'test', 'manage_options', 'tester-page', 'render_test_menu_page' );
	add_menu_page( 'GraphQL IDE', 'GraphQL', 'manage_options', 'graphiql-ide', function() {
		$rendered = apply_filters( 'graphql_render_admin_page', '<div class="wrap"><div id="graphiql" class="graphiql-container">Loading ...</div></div>' );
		echo $rendered;
	} );
	add_submenu_page( 'graphiql-ide', 'Settings', 'Settings', 'manage_options', 'graphql', 'graphql_render_settings_page' );

	add_menu_page(
		__( 'My first Gutenberg app', 'gutenberg' ),
		__( 'My first Gutenberg app', 'gutenberg' ),
		'manage_options',
		'my-first-gutenberg-app',
		function () {
			echo '
			<h2>Pages</h2>
			<div id="my-first-gutenberg-app"></div>
		';
		},
		'dashicons-schedule',
		3
	);

} );

function load_custom_wp_admin_scripts( $hook ) {
	// Load only on ?page=my-first-gutenberg-app.
	if ( 'toplevel_page_my-first-gutenberg-app' !== $hook ) {
		return;
	}

	// Load the required WordPress packages.

	// Automatically load imported dependencies and assets version.
	$asset_file = include plugin_dir_path( __FILE__ ) . 'build/index.asset.php';

	// Load our app.js.
	wp_register_script(
		'my-first-gutenberg-app',
		plugins_url( 'build/index.js', __FILE__ ),
		$asset_file['dependencies'],
		$asset_file['version']
	);
	wp_enqueue_script( 'my-first-gutenberg-app' );
}

add_action( 'admin_enqueue_scripts', 'load_custom_wp_admin_scripts' );

function graphql_render_settings_page() {
	$settings_page = '<div class=\"wrap\">';
		$settings_page .= '<h2>Settings</h2>';
		$settings_page .= apply_filters( 'graphql_render_settings_page', '' );
	$settings_page .= '</div>';
	echo $settings_page;
}

function render_test_menu_page() {
	echo '<h2>Tester...</h2>';
}


