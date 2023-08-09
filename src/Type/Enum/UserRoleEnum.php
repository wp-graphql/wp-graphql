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
		global $wp_roles;
		$all_roles      = $wp_roles->roles;
		$editable_roles = apply_filters( 'editable_roles', $all_roles );
		$roles          = [];

		if ( ! empty( $editable_roles ) && is_array( $editable_roles ) ) {
			foreach ( $editable_roles as $key => $role ) {
				$formatted_role = WPEnumType::get_safe_name( isset( $role['name'] ) ? $role['name'] : $key );

				$roles[ $formatted_role ] = [
					'description' => __( 'User role with specific capabilities', 'wp-graphql' ),
					'value'       => $key,
				];
			}
		}

		if ( ! empty( $roles ) && is_array( $roles ) ) {
			register_graphql_enum_type(
				'UserRoleEnum',
				[
					'description' => __( 'Names of available user roles', 'wp-graphql' ),
					'values'      => $roles,
				]
			);
		}
	}
}
