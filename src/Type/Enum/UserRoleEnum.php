<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Type\WPEnumType;

class UserRoleEnum {

	/**
	 * Register the UserRoleEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		$all_roles = wp_roles()->roles;
		$roles     = [];

		foreach ( $all_roles as $key => $role ) {
			$formatted_role = WPEnumType::get_safe_name( isset( $role['name'] ) ? $role['name'] : $key );

			$roles[ $formatted_role ] = [
				'description' => __( 'User role with specific capabilities', 'wp-graphql' ),
				'value'       => $key,
			];
		}

		// Bail if there are no roles to register.
		if ( empty( $roles ) ) {
			return;
		}

		register_graphql_enum_type(
			'UserRoleEnum',
			[
				'description' => __( 'Names of available user roles', 'wp-graphql' ),
				'values'      => $roles,
			]
		);
	}
}
