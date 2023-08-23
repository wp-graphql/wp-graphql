<?php
/**
 * Plugin Name: WPGraphQL
 * Plugin URI: https://github.com/wp-graphql/wp-graphql
 * GitHub Plugin URI: https://github.com/wp-graphql/wp-graphql
 * Description: GraphQL API for WordPress
 * Author: WPGraphQL
 * Author URI: http://www.wpgraphql.com
 * Version: 1.14.10
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
 * @version  1.14.10
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

add_filter('graphql_PostObjectsConnectionOrderbyEnum_values', function ($values) {

	$values['EVENT_DATE'] = [
		'value' => 'event_date',
		'description' => __('The event date (stored in meta)', 'wp-graphql'),
	];

	$values['EVENT_PRICE'] = [
		'value' => 'price',
		'description' => __('The event price (stored in meta)', 'wp-graphql'),
	];

	return $values;
});

add_filter('graphql_post_object_connection_query_args', function ($query_args, $source, $input) {

	global $wpdb;

	if ( isset($input['where']['orderby'] ) && is_array($input['where']['orderby'] ) ) {

		$new_orderby = isset( $query_args['orderby'] ) ? $query_args['orderby'] : [];

		if ( ! is_array( $new_orderby ) ) {
			$new_orderby = [ $query_args['orderby'] ];
		}

		foreach ( $input['where']['orderby'] as $orderby ) {

			if ( ! isset( $orderby['field'] ) || ! in_array( $orderby['field'], [ 'event_date', 'price' ], true ) ) {
				continue;
			}

			$meta_query = isset( $query_args['meta_query'] ) ? $query_args['meta_query'] : [];

			// set the relation
			$meta_query['relation'] = isset( $meta_query['relation'] ) ? $meta_query['relation'] : 'AND';

			$type = 'NUMERIC';

			if ( 'event_date' === $orderby['field'] ) {
				$type = 'DATETIME';
			}

			if ( 'price' === $orderby['field'] ) {
				$type = 'DECIMAL';
			}

			// Add the claus
			$meta_query[ $orderby['field'] . '_claus' ] = [
				'key' => $orderby['field'],
				'compare' => 'EXISTS',
				'type' => $type,
			];


			$new_orderby[ $orderby['field'] . '_claus' ] = $orderby['order'];

			$query_args['orderby'] = $new_orderby;
			$query_args['meta_query'] = $meta_query;

		}
	}

	return $query_args;
}, 10, 3);
