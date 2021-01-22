<?php

namespace WPGraphQL\Type\Object;

use GraphQL\Error\UserError;
use WPGraphQL\Data\DataSource;

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
	 * @return void
	 */
	public static function register_type() {

		register_graphql_object_type(
			'Settings',
			[
				'description' => __( 'All of the registered settings', 'wp-graphql' ),
				'fields'      => self::get_fields(),
			]
		);

	}

	/**
	 * Returns an array of fields for all settings based on the `register_setting` WordPress API
	 *
	 * @return array
	 */
	public static function get_fields() {
		$registered_settings = DataSource::get_allowed_settings();
		$fields              = [];

		if ( ! empty( $registered_settings ) && is_array( $registered_settings ) ) {

			/**
			 * Loop through the $settings_array and build thevar
			 * setting with
			 * proper fields
			 */
			foreach ( $registered_settings as $key => $setting_field ) {

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

				$group = lcfirst( preg_replace( '[^a-zA-Z0-9 -]', ' ', $setting_field['group'] ) );
				$group = lcfirst( str_replace( '_', ' ', ucwords( $group, '_' ) ) );
				$group = lcfirst( str_replace( '-', ' ', ucwords( $group, '_' ) ) );
				$group = lcfirst( str_replace( ' ', '', ucwords( $group, ' ' ) ) );

				$field_key = lcfirst( preg_replace( '[^a-zA-Z0-9 -]', ' ', $field_key ) );
				$field_key = lcfirst( str_replace( '_', ' ', ucwords( $field_key, '_' ) ) );
				$field_key = lcfirst( str_replace( '-', ' ', ucwords( $field_key, '_' ) ) );
				$field_key = lcfirst( str_replace( ' ', '', ucwords( $field_key, ' ' ) ) );

				$field_key = $group . 'Settings' . ucfirst( $field_key );

				if ( ! empty( $key ) && ! empty( $field_key ) ) {

					/**
					 * Dynamically build the individual setting and it's fields
					 * then add it to $fields
					 */
					$fields[ $field_key ] = [
						'type'        => $setting_field['type'],
						'description' => $setting_field['description'],

						'resolve'     => function( $root, $args, $context, $info ) use ( $setting_field, $key ) {
							/**
							 * Check to see if the user querying the email field has the 'manage_options' capability
							 * All other options should be public by default
							 */
							if ( 'admin_email' === $key && ! current_user_can( 'manage_options' ) ) {
								throw new UserError( __( 'Sorry, you do not have permission to view this setting.', 'wp-graphql' ) );
							}

							$option = ! empty( $key ) ? get_option( (string) $key ) : null;

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




