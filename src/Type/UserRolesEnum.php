<?php
namespace WPGraphQL\Type;

class UserRolesEnum {
	public static function register_type() {
		global $wp_roles;
		$all_roles      = $wp_roles->roles;
		$editable_roles = apply_filters( 'editable_roles', $all_roles );
		$roles          = [];

		if ( ! empty( $editable_roles ) && is_array( $editable_roles ) ) {
			foreach ( $editable_roles as $key => $role ) {

				$formatted_role = WPEnumType::get_safe_name( $role['name'] );

				$roles[ $formatted_role ] = [
					'value' => $key,
				];
			}
		}

		register_graphql_enum_type( 'UserRolesEnum', [
			'description' => __( 'Names of available User Roles', 'wp-graphql' ),
			'values' => $roles
		]);
	}
}