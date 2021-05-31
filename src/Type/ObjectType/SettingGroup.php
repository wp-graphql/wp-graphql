<?php

namespace WPGraphQL\Type\ObjectType;

use GraphQL\Error\UserError;
use WPGraphQL\Data\DataSource;

class SettingGroup {

	/**
	 * Register each settings group to the GraphQL Schema
	 *
	 * @param string $group_name The name of the setting group
	 * @param string $group      The settings group config
	 *
	 * @return void
	 */
	public static function register_settings_group( string $group_name, string $group ) {
		register_graphql_object_type(
			ucfirst( $group_name ) . 'Settings',
			[
				'description' => sprintf( __( 'The %s setting type', 'wp-graphql' ), $group_name ),
				'fields'      => self::get_settings_group_fields( $group_name, $group ),
			]
		);
	}

	/**
	 * Given the name of a registered settings group, retrieve GraphQL fields for the group
	 *
	 * @param string $group_name Name of the settings group to retrieve fields for
	 * @param string $group      The settings group config
	 *
	 * @return array
	 */
	public static function get_settings_group_fields( string $group_name, string $group ) {

		$setting_fields = DataSource::get_setting_group_fields( $group );

		$fields = [];

		if ( ! empty( $setting_fields ) && is_array( $setting_fields ) ) {

			foreach ( $setting_fields as $key => $setting_field ) {

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

				$field_key = lcfirst( preg_replace( '[^a-zA-Z0-9 -]', ' ', $field_key ) );
				$field_key = lcfirst( str_replace( '_', ' ', ucwords( $field_key, '_' ) ) );
				$field_key = lcfirst( str_replace( '-', ' ', ucwords( $field_key, '_' ) ) );
				$field_key = lcfirst( str_replace( ' ', '', ucwords( $field_key, ' ' ) ) );

				if ( ! empty( $key ) && ! empty( $field_key ) ) {

					/**
					 * Dynamically build the individual setting and it's fields
					 * then add it to the fields array
					 */
					$fields[ $field_key ] = [
						'type'        => $setting_field['type'],
						'description' => isset( $setting_field['description'] ) && ! empty( $setting_field['description'] ) ? $setting_field['description'] : sprintf( __( 'The %s Settings Group', 'wp-graphql' ), $setting_field['type'] ),
						'resolve'     => function( $root, array $args, $context, $info ) use ( $setting_field ) {

							/**
							 * Check to see if the user querying the email field has the 'manage_options' capability
							 * All other options should be public by default
							 */
							if ( 'admin_email' === $setting_field['key'] ) {
								if ( ! current_user_can( 'manage_options' ) ) {
									throw new UserError( __( 'Sorry, you do not have permission to view this setting.', 'wp-graphql' ) );
								}
							}

							$option = ! empty( $setting_field['key'] ) ? get_option( $setting_field['key'] ) : null;

							switch ( $setting_field['type'] ) {
								case 'integer':
								case 'int':
									return absint( $option );
								case 'string':
									return (string) $option;
								case 'boolean':
								case 'bool':
									return (bool) $option;
								case 'number':
								case 'float':
									return (float) $option;
							}

							return ! empty( $option ) ? $option : null;
						},
					];

				}
			}
		}

		return $fields;

	}

}
