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
	 */
	public static function register_connections() {

		/**
		 * Register the RootQuery connection
		 */
		register_graphql_connection( [
			'fromType'      => 'RootQuery',
			'toType'        => 'UserRole',
			'fromFieldName' => 'userRoles',
			'resolve'       => function ( $root, $args, $context, $info ) {
				return DataSource::resolve_user_role_connection( $root, $args, $context, $info );
			}
		] );
	}
}