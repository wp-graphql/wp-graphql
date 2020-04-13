<?php

namespace WPGraphQL\Data\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Model\User;

/**
 * Class PluginConnectionResolver - Connects plugins to other objects
 *
 * @package WPGraphQL\Data\Resolvers
 * @since 0.0.5
 */
class UserRoleConnectionResolver {

	/**
	 * Creates the connection for plugins
	 *
	 * @param mixed       $source  The query results
	 * @param array       $args    The query arguments
	 * @param AppContext  $context The AppContext object
	 * @param ResolveInfo $info    The ResolveInfo object
	 *
	 * @since  0.5.0
	 * @return array
	 * @throws \Exception Throws Exception.
	 */
	public static function resolve( $source, array $args, AppContext $context, ResolveInfo $info ) {

		$current_user_roles = wp_get_current_user()->roles;

		if ( $source instanceof User ) {
			$roles = ! empty( $source->roles ) ? $source->roles : [];
		} else {
			$wp_roles = wp_roles();
			$roles    = ! empty( $wp_roles->get_names() ) ? array_keys( $wp_roles->get_names() ) : [];
		}

		$roles = ! empty( $roles ) ? array_filter(
			array_map(
				function( $role ) use ( $current_user_roles ) {
					if ( current_user_can( 'list_users' ) ) {
						return $role;
					}

					if ( in_array( $role, $current_user_roles, true ) ) {
						return $role;
					}

					return null;
				},
				$roles
			)
		) : [];

		$connection = Relay::connectionFromArray( $roles, $args );

		$nodes = [];
		if ( ! empty( $connection['edges'] ) && is_array( $connection['edges'] ) ) {
			foreach ( $connection['edges'] as $edge ) {
				$nodes[] = ! empty( $edge['node'] ) ? $edge['node'] : null;
			}
		}
		$connection['nodes'] = ! empty( $nodes ) ? $nodes : null;

		return ! empty( $roles ) ? $connection : null;

	}

}
