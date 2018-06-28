<?php

namespace WPGraphQL\Type\UserRoles;


use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

/**
 * Class UserRoleQuery
 *
 * @package WPGraphQL\Type\UserRoles
 * @since 0.0.30
 */
class UserRoleQuery {

	/**
	 * Stores the definition of the root query
	 *
	 * @var array $root_query
	 */
	private static $root_query;

	/**
	 * Builds out the root query
	 *
	 * @access public
	 * @return array
	 */
	public static function root_query() {

		if ( null === self::$root_query ) {
			self::$root_query = [
				'type'        => Types::user_role(),
				'description' => __( 'Returns a user role', 'wp-graphql' ),
				'args'        => [
					'id' => Types::non_null( Types::id() ),
				],
				'resolve'     => function ( $source, array $args, AppContext $context, ResolveInfo $info ) {

					if ( current_user_can( 'list_users' ) ) {
						$id_components = Relay::fromGlobalId( $args['id'] );
						return DataSource::resolve_user_role( $id_components['id'] );
					} else {
						throw new UserError( __( 'The current user does not have the proper privileges to query this data', 'wp-graphql' ) );
					}

				}
			];
		}

		return self::$root_query;

	}

}
