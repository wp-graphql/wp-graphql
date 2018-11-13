<?php

namespace WPGraphQL\Connection;

use WPGraphQL\Data\DataSource;

/**
 * Class Sidebars
 *
 * This class organizes the registration of connections to Sidebars
 *
 * @package WPGraphQL\Connection
 */
class Sidebars {

	/**
	 * Register connections to Sidebars
	 */
	public static function register_connections() {
		/**
		 * Register connection from RootQuery to Sidebars
		 */
		register_graphql_connection( self::get_connection_config() );
	}

	/**
	 * Given an array of $args, this returns the connection config, merging the provided args
	 * with the defaults
	 *
	 * @param array $args
	 * 
	 * @return array
	 */
	protected static function get_connection_config( $args = [] ) {
		$defaults = [
			'fromType'			=> 'RootQuery',
			'toType'			=> 'Sidebar',
			'fromFieldName'		=> 'sidebars',
			'connectionArgs'	=> [],
			'resolve'			=> function ( $root, $args, $context, $info ) {
				return DataSource::resolve_sidebars_connection( $root, $args, $context, $info );
			},
		];

		return array_merge( $defaults, $args );
	}
}
