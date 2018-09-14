<?php
namespace WPGraphQL\Connection;

use WPGraphQL\Type\MenuItem\Connection\MenuItemConnectionResolver;

class MenuItems {

	public static function register_connection( array $config = [] ) {
		return;
		$default = [
			'fromType' => 'RootQuery',
			'toType' => 'MenuItem',
			'fromFieldName' => 'menuItems',
			'connectionArgs' => [
				'id' => [
					'type'        => 'Int',
					'description' => __( 'The ID of the object', 'wp-graphql' ),
				],
				'location' => [
					'type'        => 'MenuLocationEnum',
					'description' => __( 'The menu location for the menu being queried', 'wp-graphql' ),
				],
			],
			'connectionFields' => [
				'nodes' => [
					'type' => [
						'list_of' => 'MenuItem',
					],
					'description' => __( 'The nodes of the connection, without the edges', 'wp-graphql' ),
					'resolve'     => function( $source, $args, $context, $info ) {
						return ! empty( $source['nodes'] ) ? $source['nodes'] : [];
					},
				],
			],
			'resolve' => function( $source, $args, $context, $info ) {
				return MenuItemConnectionResolver::resolve( $source, $args, $context, $info );
			},
		];

		$config = array_merge( $default, $config );

		register_graphql_connection( $config );
	}
}