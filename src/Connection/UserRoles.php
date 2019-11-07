<?php

namespace WPGraphQL\Connection;

use WPGraphQL\Data\DataSource;

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
	 *
	 * @access public
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
				'resolveNode'   => function( $role ) {
					return DataSource::resolve_user_role( $role );
				},
				'resolve'       => function( $user, $args, $context, $info ) {
					return DataSource::resolve_user_role_connection( $user, $args, $context, $info );
				},
			],
			$config
		);
	}
}
