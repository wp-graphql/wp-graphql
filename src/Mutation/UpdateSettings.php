<?php

namespace WPGraphQL\Mutation;

use Exception;
use GraphQL\Error\UserError;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Registry\TypeRegistry;
use WPGraphQL\Utils\Utils;

/**
 * Class UpdateSettings
 *
 * @package WPGraphQL\Mutation
 */
class UpdateSettings {

	/**
	 * Registers the CommentCreate mutation.
	 *
	 * @param TypeRegistry $type_registry The WPGraphQL TypeRegistry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_mutation( TypeRegistry $type_registry ) {

		$output_fields = self::get_output_fields( $type_registry );
		$input_fields  = self::get_input_fields( $type_registry );

		if ( empty( $output_fields ) || empty( $input_fields ) ) {
			return;
		}

		register_graphql_mutation(
			'updateSettings',
			[
				'inputFields'         => $input_fields,
				'outputFields'        => $output_fields,
				'mutateAndGetPayload' => function ( $input ) use ( $type_registry ) {
					return self::mutate_and_get_payload( $input, $type_registry );
				},
			]
		);
	}

	/**
	 * Defines the mutation input field configuration.
	 *
	 * @param TypeRegistry $type_registry The WPGraphQL TypeRegistry
	 *
	 * @return array
	 */
	public static function get_input_fields( TypeRegistry $type_registry ) {
		$allowed_settings = DataSource::get_allowed_settings( $type_registry );

		$input_fields = [];

		if ( ! empty( $allowed_settings ) ) {

			/**
			 * Loop through the $allowed_settings and build fields
			 * for the individual settings
			 */
			foreach ( $allowed_settings as $key => $setting ) {

				if ( ! isset( $setting['type'] ) || ! $type_registry->get_type( $setting['type'] ) ) {
					continue;
				}

				/**
				 * Determine if the individual setting already has a
				 * REST API name, if not use the option name.
				 * Sanitize the field name to be camelcase
				 */
				if ( ! empty( $setting['show_in_rest']['name'] ) ) {
					$individual_setting_key = lcfirst( $setting['group'] . 'Settings' . str_replace( '_', '', ucwords( $setting['show_in_rest']['name'], '_' ) ) );
				} else {
					$individual_setting_key = lcfirst( $setting['group'] . 'Settings' . str_replace( '_', '', ucwords( $key, '_' ) ) );
				}

				$replaced_setting_key = preg_replace( '[^a-zA-Z0-9 -]', ' ', $individual_setting_key );

				if ( ! empty( $replaced_setting_key ) ) {
					$individual_setting_key = $replaced_setting_key;
				}

				$individual_setting_key = lcfirst( $individual_setting_key );
				$individual_setting_key = lcfirst( str_replace( '_', ' ', ucwords( $individual_setting_key, '_' ) ) );
				$individual_setting_key = lcfirst( str_replace( '-', ' ', ucwords( $individual_setting_key, '_' ) ) );
				$individual_setting_key = lcfirst( str_replace( ' ', '', ucwords( $individual_setting_key, ' ' ) ) );

				/**
				 * Dynamically build the individual setting,
				 * then add it to the $input_fields
				 */
				$input_fields[ $individual_setting_key ] = [
					'type'        => $setting['type'],
					'description' => $setting['description'],
				];

			}
		}

		return $input_fields;
	}

	/**
	 * Defines the mutation output field configuration.
	 *
	 * @param TypeRegistry $type_registry The WPGraphQL TypeRegistry
	 *
	 * @return array
	 */
	public static function get_output_fields( TypeRegistry $type_registry ) {

		/**
		 * Get the allowed setting groups and their fields
		 */
		$allowed_setting_groups = DataSource::get_allowed_settings_by_group( $type_registry );

		/**
		 * Get all of the settings, regardless of group
		 */
		$output_fields['allSettings'] = [
			'type'        => 'Settings',
			'description' => __( 'Update all settings.', 'wp-graphql' ),
			'resolve'     => function () {
				return true;
			},
		];

		if ( ! empty( $allowed_setting_groups ) && is_array( $allowed_setting_groups ) ) {
			foreach ( $allowed_setting_groups as $group => $setting_type ) {

				$setting_type      = DataSource::format_group_name( $group );
				$setting_type_name = Utils::format_type_name( $setting_type . 'Settings' );

				$output_fields[ Utils::format_field_name( $setting_type_name ) ] = [
					'type'        => $setting_type_name,
					'description' => sprintf( __( 'Update the %s setting.', 'wp-graphql' ), $setting_type_name ),
					'resolve'     => function () use ( $setting_type_name ) {
						return $setting_type_name;
					},
				];

			}
		}
		return $output_fields;
	}

	/**
	 * Defines the mutation data modification closure.
	 *
	 * @param array $input The mutation input
	 * @param TypeRegistry $type_registry The WPGraphQL TypeRegistry
	 *
	 * @return array
	 *
	 * @throws UserError
	 */
	public static function mutate_and_get_payload( array $input, TypeRegistry $type_registry ): array {
		/**
		 * Check that the user can manage setting options
		 */
		if ( ! current_user_can( 'manage_options' ) ) {
			throw new UserError( __( 'Sorry, you are not allowed to edit settings as this user.', 'wp-graphql' ) );
		}

		/**
		 * The $updatable_settings_options will store all of the allowed
		 * settings in a WP ready format
		 */
		$updatable_settings_options = [];

		$allowed_settings = DataSource::get_allowed_settings( $type_registry );

		/**
		 * Loop through the $allowed_settings and build the insert options array
		 */
		foreach ( $allowed_settings as $key => $setting ) {

			/**
			 * Determine if the individual setting already has a
			 * REST API name, if not use the option name.
			 * Sanitize the field name to be camelcase
			 */
			if ( isset( $setting['show_in_rest']['name'] ) && ! empty( $setting['show_in_rest']['name'] ) ) {
				$individual_setting_key = lcfirst( $setting['group'] . 'Settings' . str_replace( '_', '', ucwords( $setting['show_in_rest']['name'], '_' ) ) );
			} else {
				$individual_setting_key = lcfirst( $setting['group'] . 'Settings' . str_replace( '_', '', ucwords( $key, '_' ) ) );
			}

			/**
			 * Dynamically build the individual setting,
			 * then add it to $updatable_settings_options
			 */
			$updatable_settings_options[ Utils::format_field_name( $individual_setting_key ) ] = [
				'option' => $key,
				'group'  => $setting['group'],
			];

		}

		foreach ( $input as $key => $value ) {
			/**
			 * Throw an error if the input field is the site url,
			 * as we do not want users changing it and breaking all
			 * the things
			 */
			if ( 'generalSettingsUrl' === $key ) {
				throw new UserError( __( 'Sorry, that is not allowed, speak with your site administrator to change the site URL.', 'wp-graphql' ) );
			}

			/**
			 * Check to see that the input field exists in settings, if so grab the option
			 * name and update the option
			 */
			if ( array_key_exists( $key, $updatable_settings_options ) ) {
				update_option( $updatable_settings_options[ $key ]['option'], $value );
			}
		}

		return $updatable_settings_options;
	}
}
