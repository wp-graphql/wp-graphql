<?php

namespace WPGraphQL\Connection;

use WPGraphQL\Data\Connection\MenuItemConnectionResolver;
use WPGraphQL\Data\DataSource;

/**
 * Class MenuItems
 *
 * This class organizes registration of connections to MenuItems
 *
 * @package WPGraphQL\Connection
 */
class MenuItems {

	/**
	 * Register connections to MenuItems
	 *
	 * @access public
	 */
	public static function register_connections() {

		/**
		 * Register the RootQueryToMenuItemsConnection
		 */
		register_graphql_connection( self::get_connection_config() );

		/**
		 * Registers the ChildItems connection to the MenuItem Type
		 * MenuItemToMenuItemConnection
		 */
		register_graphql_connection(
			self::get_connection_config(
				[
					'fromType'      => 'MenuItem',
					'fromFieldName' => 'childItems',
				]
			)
		);

		/**
		 * Register the MenuToMenuItemsConnection
		 */
		register_graphql_connection(
			self::get_connection_config(
				[
					'fromType' => 'Menu',
				]
			)
		);

	}

	/**
	 * Given an array of $args, returns the args for the connection with the provided args merged
	 *
	 * @access public
	 * @param array $args
	 *
	 * @return array
	 */
	public static function get_connection_config( $args = [] ) {
		return array_merge(
			[
				'fromType'       => 'RootQuery',
				'fromFieldName'  => 'menuItems',
				'toType'         => 'MenuItem',
				'connectionArgs' => [
					'id'       => [
						'type'        => 'Int',
						'description' => __( 'The ID of the object', 'wp-graphql' ),
					],
					'location' => [
						'type'        => 'MenuLocationEnum',
						'description' => __( 'The menu location for the menu being queried', 'wp-graphql' ),
					],
				],
				'resolveNode'    => function( $id, $args, $context, $info ) {
					return ! empty( $id ) ? DataSource::resolve_menu_item( $id, $context ) : null;
				},
				'resolve'        => function ( $source, $args, $context, $info ) {
					$resolver   = new MenuItemConnectionResolver( $source, $args, $context, $info );
					$connection = $resolver->get_connection();
					return $connection;
				},
			],
			$args
		);
	}

}
