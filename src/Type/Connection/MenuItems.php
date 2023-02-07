<?php

namespace WPGraphQL\Type\Connection;

use Exception;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\Connection\MenuItemConnectionResolver;
use WPGraphQL\Model\Menu;
use WPGraphQL\Model\MenuItem;

/**
 * Class MenuItems
 *
 * This class organizes registration of connections to MenuItems
 *
 * @package WPGraphQL\Type\Connection
 */
class MenuItems {

	/**
	 * Register connections to MenuItems
	 *
	 * @return void
	 * @throws \Exception
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
					'resolve'       => function ( MenuItem $menu_item, $args, AppContext $context, ResolveInfo $info ) {

						if ( empty( $menu_item->menuId ) || empty( $menu_item->databaseId ) ) {
							return null;
						}

						$resolver = new MenuItemConnectionResolver( $menu_item, $args, $context, $info );
						$resolver->set_query_arg( 'nav_menu', $menu_item->menuId );
						$resolver->set_query_arg( 'meta_key', '_menu_item_menu_item_parent' );
						$resolver->set_query_arg( 'meta_value', (int) $menu_item->databaseId );
						return $resolver->get_connection();

					},
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
					'toType'   => 'MenuItem',
					'resolve'  => function ( Menu $menu, $args, AppContext $context, ResolveInfo $info ) {

						$resolver = new MenuItemConnectionResolver( $menu, $args, $context, $info );
						$resolver->set_query_arg( 'tax_query', [
							[
								'taxonomy'         => 'nav_menu',
								'field'            => 'term_id',
								'terms'            => (int) $menu->menuId,
								'include_children' => true,
								'operator'         => 'IN',
							],
						] );

						return $resolver->get_connection();
					},
				]
			)
		);

	}

	/**
	 * Given an array of $args, returns the args for the connection with the provided args merged
	 *
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
					'id'               => [
						'type'        => 'Int',
						'description' => __( 'The database ID of the object', 'wp-graphql' ),
					],
					'location'         => [
						'type'        => 'MenuLocationEnum',
						'description' => __( 'The menu location for the menu being queried', 'wp-graphql' ),
					],
					'parentId'         => [
						'type'        => 'ID',
						'description' => __( 'The ID of the parent menu object', 'wp-graphql' ),
					],
					'parentDatabaseId' => [
						'type'        => 'Int',
						'description' => __( 'The database ID of the parent menu object', 'wp-graphql' ),
					],
				],
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
