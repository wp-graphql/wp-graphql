<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Type\WPEnumType;

class UsersConnectionSearchColumnEnum {
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

		if ( ! empty( $roles ) && is_array( $roles ) ) {
			register_graphql_enum_type(
				'UsersConnectionSearchColumnEnum',
				[
					'description' => __( 'Names of available user roles', 'wp-graphql' ),
					'values'      => $roles,
				]
			);
		}
	}
}
