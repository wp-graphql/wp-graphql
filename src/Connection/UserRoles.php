<?php
namespace WPGraphQL\Connection;

use WPGraphQL\Data\DataSource;

class UserRoles {
	public static function register_connections() {
		register_graphql_connection([
			'fromType' => 'RootQuery',
			'toType' => 'UserRole',
			'fromFieldName' => 'userRoles',
			'resolve' => function( $root, $args, $context, $info ) {
				return DataSource::resolve_user_role_connection( $root, $args, $context, $info );
			}
		]);
	}
}