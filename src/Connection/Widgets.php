<?php

namespace WPGraphQL\Connection;

use WPGraphQL\Data\DataSource;

/**
 * Class Widgets
 *
 * This class organizes the registration of connections to Widgets
 *
 * @package WPGraphQL\Connection
 */
class Widgets {

	/**
	 * Register connections to Widgets
	 */
	public static function register_connections() {
		/**
		 * Register connection from RootQuery to Widget
		 */
    register_graphql_connection( self::get_connection_config() );
    
    /**
		 * Register connection from Sidebar to Widgets
		 */
		register_graphql_connection( self::get_connection_config( [ 'fromType' => 'Sidebar' ] ) );
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
			'toType'			=> 'WidgetInterface',
			'fromFieldName'		=> 'widgets',
			'connectionArgs'	=> [],
			'resolve'					=> function ( $root, $args, $context, $info ) {
				return DataSource::resolve_widgets_connection( $root, $args, $context, $info );
			},
    ];
    
    return array_merge( $defaults, $args );
	}
}