<?php

namespace WPGraphQL\Type\ObjectType;

use GraphQL\Error\UserError;
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

				/**
				 * Determine if the individual setting already has a
				 * REST API name, if not use the option name.
				 * Then, sanitize the field name to be camelcase
				 */
				if ( ! empty( $setting_field['show_in_rest']['name'] ) ) {
					$field_key = $setting_field['show_in_rest']['name'];
				} else {
					$field_key = $key;
				}

				$group = DataSource::format_group_name( $setting_field['group'] );

				$field_key = lcfirst( graphql_format_name( $field_key, ' ', '/[^a-zA-Z0-9 -]/' ) );
				$field_key = lcfirst( str_replace( '_', ' ', ucwords( $field_key, '_' ) ) );
				$field_key = lcfirst( str_replace( '-', ' ', ucwords( $field_key, '_' ) ) );
				$field_key = lcfirst( str_replace( ' ', '', ucwords( $field_key, ' ' ) ) );

				$field_key = $group . 'Settings' . ucfirst( $field_key );

				if ( ! empty( $key ) ) {

					/**
					 * Dynamically build the individual setting and it's fields
					 * then add it to $fields
					 */
					$fields[ $field_key ] = [
						'type'        => $setting_field['type'],
						// translators: %s is the name of the setting group.
						'description' => static function () use ( $setting_field ) {
							// translators: %s is the name of the setting group.
							return sprintf( __( 'Settings of the the %s Settings Group', 'wp-graphql' ), $setting_field['type'] );
						},
						'resolve'     => static function () use ( $setting_field, $key ) {
							/**
							 * Check to see if the user querying the email field has the 'manage_options' capability
							 * All other options should be public by default
							 */
							if ( 'admin_email' === $key && ! current_user_can( 'manage_options' ) ) {
								throw new UserError( esc_html__( 'Sorry, you do not have permission to view this setting.', 'wp-graphql' ) );
							}

							$option = get_option( (string) $key );

							switch ( $setting_field['type'] ) {
								case 'integer':
									$option = absint( $option );
									break;
								case 'string':
									$option = ! empty( $option ) ? (string) $option : '';
									break;
								case 'boolean':
									$option = (bool) $option;
									break;
								case 'number':
									$option = (float) $option;
									break;
							}

							return isset( $option ) ? $option : null;
						},
					];
				}
			}
		}

		return $fields;
	}
}
