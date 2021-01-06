<?php

namespace WPGraphQL\Connection;

use WPGraphQL\Data\Connection\MenuConnectionResolver;
use WPGraphQL\Data\Connection\MenuItemConnectionResolver;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\MenuItem;

/**
 * Class Menus
 *
 * This class organizes the registration of connections to Menus
 *
 * @package WPGraphQL\Connection
 */
class Menus {

	/**
	 * Registers connections to Menus
	 *
	 * @return void
	 */
	public static function register_connections() {

		/**
		 * Registers the RootQueryToMenuConnection
		 */
		register_graphql_connection(
			[
				'fromType'       => 'RootQuery',
				'toType'         => 'Menu',
				'fromFieldName'  => 'menus',
				'connectionArgs' => [
					'id'       => [
						'type'        => 'Int',
						'description' => __( 'The ID of the object', 'wp-graphql' ),
					],
					'location' => [
						'type'        => 'MenuLocationEnum',
						'description' => __( 'The menu location for the menu being queried', 'wp-graphql' ),
					],
					'slug'     => [
						'type'        => 'String',
						'description' => __( 'The slug of the menu to query items for', 'wp-graphql' ),
					],
				],
				'resolve'        => function ( $source, $args, $context, $info ) {
					$resolver   = new MenuConnectionResolver( $source, $args, $context, $info, 'nav_menu' );
					$connection = $resolver->get_connection();
					return $connection;
				},
			]
		);

		register_graphql_connection([
			'fromType'      => 'MenuItem',
			'toType'        => 'Menu',
			'description'   => __( 'The Menu a MenuItem is part of', 'wp-graphql' ),
			'fromFieldName' => 'menu',
			'oneToOne'      => true,
			'resolve'       => function( MenuItem $menu_item, $args, $context, $info ) {
				$resolver = new MenuConnectionResolver( $menu_item, $args, $context, $info );
				$resolver->set_query_arg( 'include', $menu_item->menuDatabaseId );
				return $resolver->one_to_one()->get_connection();
			},
		]);
	}
}
