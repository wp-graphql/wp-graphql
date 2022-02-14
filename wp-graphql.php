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
//	add_menu_page( 'GraphQL IDE', 'GraphQL', 'manage_options', 'graphiql-ide', function() {
//		$rendered = apply_filters( 'graphql_render_admin_page', '<div class="wrap"><div id="graphiql" class="graphiql-container">Loading ...</div></div>' );
//		echo $rendered;
//	} );
	add_submenu_page( 'graphiql-ide', 'Settings', 'Settings', 'manage_options', 'graphql', 'graphql_render_settings_page' );

	add_menu_page( 'GraphQL IDE', 'GraphQL', 'manage_options', 'graphiql-ide', function() {
		$rendered = apply_filters( 'graphql_render_admin_page', '<div class="wrap"><div id="graphiql" class="graphiql-container">Loading ...</div></div>' );
		echo $rendered;
	} );

} );

function load_custom_wp_admin_scripts( $hook ) {

	if ( null === get_current_screen() || false === strpos( get_current_screen()->id, 'graphiql-ide' ) ) {
		return;
	}

	$asset_file = include( plugin_dir_path( __FILE__ ) . 'build/index.asset.php');

	// Setup some globals that can be used by GraphiQL
	// and extending scripts
	wp_enqueue_script(
		'wp-graphiql', // Handle.
		plugins_url( 'build/index.js', __FILE__ ),
		$asset_file['dependencies'],
		$asset_file['version'],
		true
	);

	$app_asset_file = include( plugin_dir_path( __FILE__ ) . 'build/app.asset.php');

	wp_enqueue_script(
		'wp-graphiql-app', // Handle.
		plugins_url( 'build/app.js', __FILE__ ),
		array_merge( ['wp-graphiql'], $app_asset_file['dependencies'] ),
		$app_asset_file['version'],
		true
	);

	wp_enqueue_style(
		'wp-graphiql-app',
		plugins_url( 'build/app.css', __FILE__ ),
		[ 'wp-components' ],
		$app_asset_file['version']
	);

	wp_localize_script(
		'wp-graphiql',
		'wpGraphiQLSettings',
		[
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'graphqlEndpoint' => trailingslashit( site_url() ) . 'index.php?' . \WPGraphQL\Router::$route,
			'avatarUrl' => 0 !== get_current_user_id() ? get_avatar_url( get_current_user_id() ) : null,
			'externalFragments' => apply_filters( 'graphiql_external_fragments', [] )
		]
	);

	// Extensions looking to extend GraphiQL can hook in here,
	// after the window object is established, but before the App renders
	do_action( 'enqueue_graphiql_extension' );
}

add_action( 'admin_enqueue_scripts', 'load_custom_wp_admin_scripts' );

/**
 * Enqueue extension styles and scripts
 *
 * These extensions are part of WPGraphiQL core, but were built in a way
 * to showcase how extension APIs can be used to extend WPGraphiQL
 */
add_action( 'enqueue_graphiql_extension', 'graphiql_enqueue_query_composer' );
add_action( 'enqueue_graphiql_extension', 'graphiql_enqueue_auth_switch' );
add_action( 'enqueue_graphiql_extension', 'graphiql_enqueue_fullscreen_toggle' );

/**
 * Enqueue the GraphiQL Auth Switch extension, which adds a button to the GraphiQL toolbar
 * that allows the user to switch between the logged in user and the current user
 */
function graphiql_enqueue_auth_switch() {

	$auth_switch_asset_file = include( plugin_dir_path( __FILE__ ) . 'build/graphiqlAuthSwitch.asset.php');

	wp_enqueue_script(
		'wp-graphiql-auth-switch', // Handle.
		plugins_url( 'build/graphiqlAuthSwitch.js', __FILE__ ),
		array_merge( ['wp-graphiql', 'wp-graphiql-app'], $auth_switch_asset_file['dependencies'] ),
		$auth_switch_asset_file['version'],
		true
	);
}

/**
 * Enqueue the Query Composer extension, which adds a button to the GraphiQL toolbar
 * that allows the user to open the Query Composer and compose a query with a form-based UI
 */
function graphiql_enqueue_query_composer() {

	// Enqueue the assets for the Explorer before enqueueing the app,
	// so that the JS in the exporter that hooks into the app will be available
	// by time the app is enqueued
	$composer_asset_file = include( plugin_dir_path( __FILE__ ) . 'build/graphiqlQueryComposer.asset.php');

	wp_enqueue_script(
		'wp-graphiql-query-composer', // Handle.
		plugins_url( 'build/graphiqlQueryComposer.js', __FILE__ ),
		array_merge( ['wp-graphiql', 'wp-graphiql-app'], $composer_asset_file['dependencies'] ),
		$composer_asset_file['version'],
		true
	);

	wp_enqueue_style(
		'wp-graphiql-query-composer',
		plugins_url( 'build/graphiqlQueryComposer.css', __FILE__ ),
		[ 'wp-components' ],
		$composer_asset_file['version']
	);

}

/**
 * Enqueue the GraphiQL Fullscreen Toggle extension, which adds a button to the GraphiQL toolbar
 * that allows the user to toggle the fullscreen mode
 */
function graphiql_enqueue_fullscreen_toggle() {

	$fullscreen_toggle_asset_file = include( plugin_dir_path( __FILE__ ) . 'build/graphiqlFullscreenToggle.asset.php');

	wp_enqueue_script(
		'wp-graphiql-fullscreen-toggle', // Handle.
		plugins_url( 'build/graphiqlFullscreenToggle.js', __FILE__ ),
		array_merge( ['wp-graphiql', 'wp-graphiql-app'], $fullscreen_toggle_asset_file['dependencies'] ),
		$fullscreen_toggle_asset_file['version'],
		true
	);

	wp_enqueue_style(
		'wp-graphiql-fullscreen-toggle',
		plugins_url( 'build/graphiqlFullscreenToggle.css', __FILE__ ),
		[ 'wp-components' ],
		$fullscreen_toggle_asset_file['version']
	);

}

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


