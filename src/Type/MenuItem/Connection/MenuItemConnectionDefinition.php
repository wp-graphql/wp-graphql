<?php

namespace WPGraphQL\Type\MenuItem\Connection;

use GraphQLRelay\Relay;
use WPGraphQL\TypeRegistry;
use WPGraphQL\Types;

/**
 * Class MenuItemConnectionDefinition
 *
 * @package WPGraphQL\Type\MenuItem\Connection
 * @since   0.0.30
 */
class MenuItemConnectionDefinition {

	/**
	 * Stores the Relay connection for MenuItems
	 *
	 * @var array $connection
	 * @access private
	 */
	private static $connection;

	/**
	 * Create the Relay connection for MenuItems
	 *
	 * @return mixed
	 * @since  0.0.30
	 */
	public static function connection() {

		if ( null === self::$connection ) {

			$connection = Relay::connectionDefinitions( [
				'nodeType' => TypeRegistry::get_type( 'MenuItem' ),
				'name'     => 'MenuItems',
				'connectionFields' => function() {
					return [
						'nodes' => [
							'type'        => Types::list_of( TypeRegistry::get_type( 'MenuItem' ) ),
							'description' => __( 'The nodes of the connection, without the edges', 'wp-graphql' ),
							'resolve'     => function( $source, $args, $context, $info ) {
								return ! empty( $source['nodes'] ) ? $source['nodes'] : [];
							},
						],
					];
				},
			] );

			$args = [
				'where' => [
					'name' => 'where',
					'type' => 'MenuItemsWhereArgs',
				],
			];

			self::$connection = [
				'type'        => $connection['connectionType'],
				'description' => __( 'A collection of menu item objects', 'wp-graphql' ),
				'args'        => array_merge( Relay::connectionArgs(), $args ),
				'resolve'     => [ __NAMESPACE__ . '\\MenuItemConnectionResolver', 'resolve' ],
			];
		}

		return ! empty( self::$connection ) ? self::$connection : null;
	}

}
