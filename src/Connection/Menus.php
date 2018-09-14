<?php
namespace WPGraphQL\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Type\Menu\Connection\MenuConnectionResolver;

class Menus {

	protected static $to_type = 'Menu';

	public static function register_connection( $from_type = 'RootQuery' ) {

		register_graphql_connection([
			'fromType' => $from_type,
			'toType' => self::$to_type,
			'fromFieldName' => 'menus',
			'connectionArgs'   => [
				'id' => [
					'type'        => 'Int',
					'description' => __( 'The ID of the object', 'wp-graphql' ),
				],
				'location' => [
					'type'        => 'MenuLocationEnum',
					'description' => __( 'The menu location for the menu being queried', 'wp-graphql' ),
				],
				'slug' => [
					'type'        => 'String',
					'description' => __( 'The slug of the menu to query items for', 'wp-graphql' ),
				],
			],
			'connectionFields' => [
				'nodes' => [
					'type'        => [
						'list_of' => 'Menu'
					],
					'description' => __( 'The nodes of the connection, without the edges', 'wp-graphql' ),
					'resolve'     => function( $source, $args, $context, $info ) {
						return ! empty( $source['nodes'] ) ? $source['nodes'] : [];
					},
				],
			],
			'resolve' => function( $source, $args, AppContext $context, ResolveInfo $info ) {
				$connection = MenuConnectionResolver::resolve( $source, $args, $context, $info );
				return $connection;
			},
		]);
	}
}