<?php

namespace WPGraphQL\Type\Menu\Connection;

use GraphQLRelay\Relay;
use WPGraphQL\TypeRegistry;
use WPGraphQL\Types;

/**
 * Class MenuConnectionDefinition
 *
 * @package WPGraphQL\Type\Menu\Connection
 * @since   0.0.30
 */
class MenuConnectionDefinition {

	/**
	 * Stores the Relay connection for Menus
	 *
	 * @var array $connection
	 * @access private
	 */
	private static $connection;

	/**
	 * Create the Relay connection for Menus.
	 *
	 * @param string $from_type Connection type.
	 * @return mixed
	 * @since  0.0.30
	 */
	public static function connection( $from_type = 'Root' ) {

		if ( null === self::$connection ) {

			$connection = Relay::connectionDefinitions( [
				'nodeType' => TypeRegistry::get_type( 'Menu' ),
				'name'     => 'Menus',
				'connectionFields' => function() {
					return [
						'nodes' => [
							'type'        => Types::list_of( TypeRegistry::get_type( 'Menu' ) ),
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
					'type' => TypeRegistry::get_type( 'MenuConnectionWhereArgs' ),
				],
			];

			self::$connection = [
				'type'        => $connection['connectionType'],
				'description' => __( 'A collection of menu objects', 'wp-graphql' ),
				'args'        => array_merge( Relay::connectionArgs(), $args ),
				'resolve'     => [ __NAMESPACE__ . '\\MenuConnectionResolver', 'resolve' ],
			];
		}

		return ! empty( self::$connection ) ? self::$connection : null;
	}

}
