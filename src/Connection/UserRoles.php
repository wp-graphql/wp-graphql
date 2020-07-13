<?php

namespace WPGraphQL\Connection;

use WPGraphQL\Data\Connection\UserRoleConnectionResolver;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\User;

/**
 * Class UserRoles
 *
 * This registers the connections to UserRoles
 *
 * @package WPGraphQL\Connection
 */
class UserRoles {

	/**
	 * Register the connections
	 */
	public static function register_connections() {

		/**
		 * Register the RootQuery connection
		 */
		register_graphql_connection(
			self::get_connection_config(
				[
					'fromType'      => 'RootQuery',
					'toType'        => 'UserRole',
					'fromFieldName' => 'userRoles',
					'resolve'       => function( $user, $args, $context, $info ) {
						$resolver = new UserRoleConnectionResolver( $user, $args, $context, $info );
						return $resolver->get_connection();
					},
				]
			)
		);

		/**
		 * Register connection from User to User Roles
		 */
		register_graphql_connection(
			self::get_connection_config(
				[
					'fromType'      => 'User',
					'toType'        => 'UserRole',
					'fromFieldName' => 'roles',
					'resolve'       => function( User $user, $args, $context, $info ) {
						$resolver = new UserRoleConnectionResolver( $user, $args, $context, $info );
						// Only get roles matching the slugs of the roles belonging to the user

						if ( ! empty( $user->roles ) ) {
							$resolver->setQueryArg( 'slugIn', $user->roles );
						}

						return $resolver->get_connection();
					},
				]
			)
		);
	}

	/**
	 * Given an array of config, returns a config with the custom config merged with the defaults
	 *
	 * @param array $config
	 *
	 * @return array
	 */
	public static function get_connection_config( array $config ) {
		return array_merge(
			[
				'fromType'      => 'User',
				'toType'        => 'UserRole',
				'fromFieldName' => 'roles',
			],
			$config
		);
	}
}
