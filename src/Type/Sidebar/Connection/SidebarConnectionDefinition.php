<?php

namespace WPGraphQL\Type\Sidebar\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

/**
 * Class SidebarConnectionDefinition
 *
 * @package WPGraphQL\Type\Sidebar\Connection
 * @since   0.0.31
 */
class SidebarConnectionDefinition {

	/**
	 * Stores the Relay connection for Sidebar
	 *
	 * @var array $connection
	 * @access private
	 */
	private static $connection;

	/**
	 * Create the Relay connection for Sidebars.
	 *
	 * @param string $from_type Connection type.
	 * @return mixed
	 * @since  0.0.31
	 */
	public static function connection( $from_type = 'Root' ) {

		if ( null === self::$connection ) {

			$connection = Relay::connectionDefinitions( [
				'nodeType' => Types::sidebar(),
				'name'     => 'Sidebars',
				'connectionFields' => function() {
					return [
						'nodes' => [
							'type'        => Types::list_of( Types::sidebar() ),
							'description' => __( 'The nodes of the connection, without the edges', 'wp-graphql' ),
							'resolve'     => function( $source, $args, $context, $info ) {
								return ! empty( $source['nodes'] ) ? $source['nodes'] : [];
							},
						],
					];
				},
			] );

			self::$connection = [
				'type'        => $connection['connectionType'],
				'description' => __( 'A collection of sidebar objects', 'wp-graphql' ),
				'args'        => Relay::connectionArgs(),
				'resolve'     => function( $source, $args, AppContext $context, ResolveInfo $info ) {
					return DataSource::resolve_sidebars_connection( $source, $args, $context, $info );
				},
			];
		}

		return ! empty( self::$connection ) ? self::$connection : null;
	}

}