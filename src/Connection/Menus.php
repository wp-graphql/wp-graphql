<?php

namespace WPGraphQL\Connection;

use WPGraphQL\Data\Connection\MenuConnectionResolver;
use WPGraphQL\Data\DataSource;

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
	 * @access public
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
				'resolveNode'    => function ( $id, $args, $context, $info ) {
					return DataSource::resolve_term_object( $id, $context );
				},
				'resolve'        => function ( $source, $args, $context, $info ) {
					$resolver   = new MenuConnectionResolver( $source, $args, $context, $info, 'nav_menu' );
					$connection = $resolver->get_connection();

					return $connection;
				},
			]
		);
	}
}
