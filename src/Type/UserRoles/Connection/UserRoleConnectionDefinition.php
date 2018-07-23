<?php

namespace WPGraphQL\Type\UserRoles\Connection;


use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

/**
 * Class UserRoleConnectionDefinition
 *
 * @package WPGraphQL\Type\UserRoles\Connection
 * @since 0.0.30
 */
class UserRoleConnectionDefinition {

	/**
	 * Stores the connection info
	 *
	 * @var array $connection
	 */
	private static $connection = [];

	/**
	 * Builds out the connection definition for user roles
	 *
	 * @param string $from_type
	 *
	 * @access public
	 * @return mixed|null
	 */
	public static function connection( $from_type = 'root' ) {

		if ( empty( self::$connection[ $from_type ] ) ) {

			$connection = Relay::connectionDefinitions( [
				'nodeType'         => Types::user_role(),
				'name'             => 'UserRoles',
				'connectionFields' => function () {
					return [
						'nodes' => [
							'type'        => Types::list_of( Types::user_role() ),
							'description' => __( 'The nodes of the connection, without the edges', 'wp-graphql' ),
							'resolve'     => function ( $source, array $args, AppContext $context, ResolveInfo $info ) {
								return ! empty( $source['nodes'] ) ? $source['nodes'] : [];
							}
						]
					];
				}
			] );

			self::$connection[ $from_type ] = [
				'type'        => $connection['connectionType'],
				'description' => __( 'A collection of user roles', 'wp-graphql' ),
				'args'        => Relay::connectionArgs(),
				'resolve'     => function ( $source, $args, AppContext $context, ResolveInfo $info ) {
					return DataSource::resolve_user_role_connection( $source, $args, $context, $info );
				}
			];

		}

		return ! empty( self::$connection[ $from_type ] ) ? self::$connection[ $from_type ] : null;

	}
}