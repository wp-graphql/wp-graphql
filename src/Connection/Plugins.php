<?php

namespace WPGraphQL\Connection;

use WPGraphQL\Data\DataSource;

/**
 * Class Plugins
 *
 * This class organizes the registration of connections to Plugins
 *
 * @package WPGraphQL\Connection
 */
class Plugins {

	/**
	 * Register connections to Plugins
	 *
	 * @access public
	 */
	public static function register_connections() {

		$connection_args = self::get_connection_args();

		register_graphql_connection( [
			'fromType'      => 'RootQuery',
			'toType'        => 'Plugin',
			'fromFieldName' => 'plugins',
			'connectionArgs'   => $connection_args,
			'resolve'       => function ( $root, $args, $context, $info ) {
				return DataSource::resolve_plugins_connection( $root, $args, $context, $info );
			},
		] );
	}

	/**
	 * Given an optional array of args, this returns the args to be used in the connection
	 *
	 * @access public
	 * @param array $args The args to modify the defaults
	 *
	 * @return array
	 */
	public static function get_connection_args( $args = [] ) {

		return array_merge( [

			/**
			 * Status parameters
			 */
			'status'       => [
				'type' => 'PluginStatusEnum',
				'description'  => __( 'The status of the plugin object', 'wp-graphql' ),
			],

		], $args );
	}
}
