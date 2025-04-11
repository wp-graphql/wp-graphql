<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Type\WPEnumType;

class UserRoleEnum {
	use EnumDescriptionTrait;

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
				'description' => self::get_filtered_description( 'UserRoleEnum', $key ),
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
				'description' => __( 'Permission levels for user accounts. Defines the standard access levels that control what actions users can perform within the system.', 'wp-graphql' ),
				'values'      => $roles,
			]
		);
	}

	/**
	 * Get the default description for a user role.
	 *
	 * @param string               $value The role key (administrator, editor, etc.)
	 * @param array<string, mixed> $context Additional context data
	 */
	protected static function get_default_description( string $value, array $context = [] ): string {
		switch ( $value ) {
			case 'administrator':
				return __( 'Full system access with ability to manage all aspects of the site.', 'wp-graphql' );
			case 'editor':
				return __( 'Content management access without administrative capabilities.', 'wp-graphql' );
			case 'author':
				return __( 'Can publish and manage their own content.', 'wp-graphql' );
			case 'contributor':
				return __( 'Can write and manage their own content but cannot publish.', 'wp-graphql' );
			case 'subscriber':
				return __( 'Can only manage their profile and read content.', 'wp-graphql' );
			default:
				return __( 'User role with specific capabilities', 'wp-graphql' );
		}
	}
}
