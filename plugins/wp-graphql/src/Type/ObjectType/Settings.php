<?php

namespace WPGraphQL\Type\ObjectType;

use WPGraphQL\Data\DataSource;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class Settings
 *
 * @package WPGraphQL\Type\Object
 */
class Settings {

	/**
	 * Registers a Settings Type with fields for all settings based on settings
	 * registered using the core register_setting API
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry The WPGraphQL TypeRegistry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		$fields = self::get_fields( $type_registry );

		if ( empty( $fields ) ) {
			return;
		}

		register_graphql_object_type(
			'Settings',
			[
				'description' => static function () {
					return __( 'All of the registered settings', 'wp-graphql' );
				},
				'fields'      => $fields,
			]
		);
	}

	/**
	 * Returns an array of fields for all settings based on the `register_setting` WordPress API
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry The WPGraphQL TypeRegistry
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_fields( TypeRegistry $type_registry ) {
		$registered_settings = DataSource::get_allowed_settings( $type_registry );
		$fields              = [];

		if ( ! empty( $registered_settings ) && is_array( $registered_settings ) ) {

			/**
			 * Loop through the $settings_array and build thevar
			 * setting with
			 * proper fields
			 */
			foreach ( $registered_settings as $key => $setting_field ) {
				if ( ! isset( $setting_field['type'] ) || ! $type_registry->get_type( $setting_field['type'] ) ) {
					continue;
				}

				// The flat field name is prefixed with the group, so entries without a group can't be exposed here.
				if ( ! isset( $setting_field['group'] ) || empty( $setting_field['group'] ) ) {
					continue;
				}

				/**
				 * The flat (group-prefixed) GraphQL field name is derived once in the
				 * normalized settings map (DataSource::get_normalized_settings) so every
				 * read and write surface uses the same name.
				 */
				$field_key = isset( $setting_field['graphql_settings_field_name'] ) ? (string) $setting_field['graphql_settings_field_name'] : '';

				if ( ! empty( $key ) && ! empty( $field_key ) ) {

					// The formatted group name, passed to the setting's `graphql_resolve` callback.
					$group = DataSource::format_group_name( (string) $setting_field['group'] );

					/**
					 * Dynamically build the individual setting and it's fields
					 * then add it to $fields
					 */
					$fields[ $field_key ] = [
						'type'        => $setting_field['type'],
						// translators: %s is the name of the setting group.
						'description' => static function () use ( $setting_field ) {
							// translators: %s is the name of the setting group.
							return sprintf( __( 'Settings of the %s Settings Group', 'wp-graphql' ), $setting_field['type'] );
						},
						'resolve'     => static function ( $root, array $args, \WPGraphQL\AppContext $context ) use ( $setting_field, $group, $field_key ) {
							/**
							 * Pre-check the setting's declared `graphql_capability` at this
							 * surface so the debug message names the flat field. The Model
							 * enforces the same capability for the grouped surface.
							 */
							if ( ! empty( $setting_field['graphql_capability'] ) && ! current_user_can( (string) $setting_field['graphql_capability'] ) ) {
								$field_name = 'Settings.' . $field_key;
								graphql_debug(
									// translators: 1: GraphQL field name, 2: required WordPress capability.
									sprintf( __( 'The "%1$s" field requires the "%2$s" capability and resolved to null.', 'wp-graphql' ), $field_name, (string) $setting_field['graphql_capability'] ),
									[
										'type'  => 'RESTRICTED_FIELD',
										'field' => $field_name,
										'required_capability' => (string) $setting_field['graphql_capability'],
									]
								);

								return null;
							}

							/**
							 * The flat surface reads the value from the owning group's
							 * Model, so both read surfaces resolve a setting through the
							 * same path (option read, type cast, `graphql_resolve`, the
							 * `graphql_setting_field_value` filter).
							 */
							$grouped_field_name = isset( $setting_field['graphql_field_name'] ) ? (string) $setting_field['graphql_field_name'] : '';

							return $context->get_loader( 'setting_group' )->load_deferred( $group )->then(
								static function ( $setting_group ) use ( $grouped_field_name ) {
									if ( ! $setting_group instanceof \WPGraphQL\Model\SettingGroup || empty( $grouped_field_name ) ) {
										return null;
									}

									return $setting_group->{$grouped_field_name};
								}
							);
						},
					];
				}
			}
		}

		return $fields;
	}
}
