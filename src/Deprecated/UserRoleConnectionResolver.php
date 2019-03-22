<?php

namespace WPGraphQL\Data;


use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;

/**
 * Class UserRoleConnectionResolver
 *
 * @package WPGraphQL\Type\UserRoles\Connection
 * @since 0.0.30
 */
class UserRoleConnectionResolver {

	/**
	 * Resolve for the UserRole query
	 *
	 * @param array       $source  The Query results
	 * @param array       $args    The query arguments
	 * @param AppContext  $context The AppContext passed down to the query
	 * @param ResolveInfo $info    The ResloveInfo object
	 *
	 * @access public
	 * @throws UserError
	 * @return array
	 */
	public static function resolve( $source, array $args, AppContext $context, ResolveInfo $info ) {

		if ( ! current_user_can( 'list_users' ) ) {
			throw new UserError( __( 'The current user does not have the proper privileges to query this data', 'wp-graphql' ) );
		}

		$roles = wp_roles();
		$clean_roles = [];

		if ( is_a( $roles, 'WP_Roles' ) && is_array( $roles->roles ) && ! empty( $roles->roles ) ) {

			foreach ( $roles->roles as $role_name => $data ) {
				$data['id'] = $role_name;
				$clean_roles[] = $data;
			}

			$connection = Relay::connectionFromArray( $clean_roles, $args );

			$nodes = [];

			if ( ! empty( $connection['edges'] ) && is_array( $connection['edges'] ) ) {
				foreach ( $connection['edges'] as $edge ) {
					$nodes[] = ! empty( $edge['node'] ) ? $edge['node'] : null;
				}
			}

			$connection['nodes'] = ! empty( $nodes ) ? $nodes : null;

			return ! empty( $clean_roles ) ? $connection : null;

		} else {
			throw new UserError( __( 'No user roles could be found given the input', 'wp-graphql' ) );
		}

	}
}
