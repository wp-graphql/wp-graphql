<?php

namespace WPGraphQL\Type\ObjectType;

use WPGraphQL\Data\DataSource;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class SettingGroup
 *
 * Registers the GraphQL object types for setting groups. Not to be confused
 * with \WPGraphQL\Model\SettingGroup, the data-layer Model a settings group
 * resolves through.
 */
class SettingGroup {

	/**
	 * Given the normalized settings group key, return the GraphQL Type name
	 * registered for the group.
	 *
	 * Single source of the group-key -> type-name derivation, shared by the
	 * type registration and node-type resolution so the two cannot drift.
	 *
	 * @param string $group_name The normalized settings group key.
	 */
	public static function get_type_name( string $group_name ): string {
		return ucfirst( $group_name ) . 'Settings';
	}

	/**
	 * Register each settings group to the GraphQL Schema
	 *
	 * @param string                           $group_name    The name of the setting group
	 * @param string                           $group         The settings group config
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry The WPGraphQL TypeRegistry
	 *
	 * @return string|null
	 * @throws \Exception
	 */
	public static function register_settings_group( string $group_name, string $group, TypeRegistry $type_registry ) {
		$fields = self::get_settings_group_fields( $group_name, $group, $type_registry );

		// if the settings group doesn't have any fields that
		// will map to the WPGraphQL Schema,
		// don't register the settings group Type to the schema
		if ( empty( $fields ) ) {
			return null;
		}

		// The Node interface provides the `id` field; it is declared here only
		// to carry a settings-specific description. The value resolves through
		// the SettingGroup Model the group's root field returns.
		$fields['id'] = [
			'type'        => [ 'non_null' => 'ID' ],
			'description' => static function () {
				return __( 'The globally unique identifier of the settings group.', 'wp-graphql' );
			},
		];

		register_graphql_object_type(
			self::get_type_name( $group_name ),
			[
				// translators: %s is the name of the setting group.
				'description' => static function () use ( $group_name ) {
					// translators: %s is the name of the setting group.
					return sprintf( __( 'The %s setting type', 'wp-graphql' ), $group_name );
				},
				'interfaces'  => [ 'Node' ],
				'model'       => \WPGraphQL\Model\SettingGroup::class,
				'fields'      => $fields,
			]
		);

		return self::get_type_name( $group_name );
	}

	/**
	 * Given the name of a registered settings group, retrieve GraphQL fields for the group
	 *
	 * @param string                           $group_name Name of the settings group to retrieve fields for
	 * @param string                           $group      The settings group config
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry The WPGraphQL TypeRegistry
	 *
	 * @return array<string,array<string,mixed>>|null
	 */
	public static function get_settings_group_fields( string $group_name, string $group, TypeRegistry $type_registry ) {
		$setting_fields = DataSource::get_setting_group_fields( $group, $type_registry );
		$fields         = [];

		if ( ! empty( $setting_fields ) && is_array( $setting_fields ) ) {
			foreach ( $setting_fields as $key => $setting_field ) {
				if ( ! isset( $setting_field['type'] ) || ! $type_registry->get_type( $setting_field['type'] ) ) {
					continue;
				}

				/**
				 * The grouped GraphQL field name is derived once in the normalized
				 * settings map (DataSource::get_normalized_settings) so every read and
				 * write surface uses the same name.
				 */
				$field_key = isset( $setting_field['graphql_field_name'] ) ? (string) $setting_field['graphql_field_name'] : '';

				if ( ! empty( $key ) && ! empty( $field_key ) ) {

					/**
					 * Dynamically build the individual setting and its fields
					 * then add it to the fields array.
					 *
					 * No `resolve` is declared: the group's root field returns the
					 * SettingGroup Model, and the default resolver reads the field
					 * from the Model, which owns the value resolution (option read,
					 * type cast, `graphql_resolve`, `graphql_setting_field_value`)
					 * and any `graphql_capability` restriction.
					 */
					$fields[ $field_key ] = [
						'type'        => $type_registry->get_type( $setting_field['type'] ),
						// translators: %s is the name of the setting group.
						'description' => static function () use ( $setting_field ) {
							// translators: %s is the name of the setting group.
							return isset( $setting_field['description'] ) && ! empty( $setting_field['description'] ) ? $setting_field['description'] : sprintf( __( 'The %s Settings Group', 'wp-graphql' ), $setting_field['type'] );
						},
					];
				}
			}
		}

		return $fields;
	}

	/**
	 * Resolver for the timezone setting, assigned as the `graphql_resolve` config
	 * of the `timezone_string` entry in the normalized settings map.
	 *
	 * When a site is configured with a manual UTC offset instead of a named timezone,
	 * WordPress stores the offset in the `gmt_offset` option and leaves `timezone_string`
	 * empty. The `timezone` field maps to `timezone_string`, so without this fallback it
	 * would resolve to an empty string. Here we defer to `wp_timezone_string()`, which
	 * returns the named timezone when set and otherwise builds an offset string (e.g. `+02:00`)
	 * from `gmt_offset`.
	 *
	 * @param mixed               $value         The resolved value of the setting field.
	 * @param array<string,mixed> $setting_field The setting field config, including its `key` and `type`.
	 *
	 * @return mixed
	 *
	 * @since x-release-please-version
	 */
	public static function resolve_timezone_setting_value( $value, array $setting_field ) {
		if ( isset( $setting_field['key'] ) && 'timezone_string' === $setting_field['key'] && empty( $value ) ) {
			return wp_timezone_string();
		}

		return $value;
	}
}
