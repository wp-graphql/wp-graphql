<?php

namespace WPGraphQL\Connection;

use WPGraphQL\Data\MenuConnectionResolver;

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
	 */
	public static function register_connections() {

		/**
		 * Registers the RootQueryToMenuConnection
		 */
		register_graphql_connection( [
			'fromType'         => 'RootQuery',
			'toType'           => 'Menu',
			'fromFieldName'    => 'menus',
			'connectionArgs'   => [
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
			'connectionFields' => [
				'nodes' => [
					'type'        => [
						'list_of' => 'Menu',
					],
					'description' => __( 'The nodes of the connection, without the edges', 'wp-graphql' ),
					'resolve'     => function ( $source, $args, $context, $info ) {
						return ! empty( $source['nodes'] ) ? $source['nodes'] : [];
					},
				],
			],
			'resolve'          => function ( $source, $args, $context, $info ) {
				return MenuConnectionResolver::resolve( $source, $args, $context, $info );
			},
		] );
	}
}