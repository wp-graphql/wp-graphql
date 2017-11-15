<?php

namespace WPGraphQL\Type\Settings\Mutation;

use WPGraphQL\Types;
use WPGraphQL\Data\DataSource;

/**
 * Class SettingMutation
 *
 * @package WPGraphQL\Type\Setting
 */
class SettingsMutation {

	/**
	 * Holds the input fields configuration
	 */
	private static $input_fields;

	/**
	 * The input fields for the settings mutation
	 *
	 * @param array $settings_array
	 *
	 * @return mixed|array|null $input_fields
	 */
	public static function input_fields() {

		/**
		 * Retrieve all of the allowed settings
		 */
		$settings_array = DataSource::get_allowed_settings();

		$input_fields = [];

		if ( ! empty( $settings_array ) && empty( self::$input_fields ) ) {

			/**
			 * Loop through the $setting_type_array and build the setting with
			 * proper fields
			 */
			foreach ( $settings_array as $key => $setting ) {

				/**
				 * Determine if the individual setting already has a
				 * REST API name, if not use the option name (setting).
				 * Sanitize the field name to be camelcase
				 */
				if ( ! empty( $setting['show_in_rest']['name'] ) ) {
					$individual_setting_key = lcfirst( $setting['group'] . 'Settings' . str_replace( '_', '', ucwords( $setting['show_in_rest']['name'], '_' ) ) );
				} else {
					$individual_setting_key = lcfirst( $setting['group'] . 'Settings' . str_replace( '_', '', ucwords( $key, '_' ) ) );
				}

				/**
				 * Dynamically build the individual setting and it's fields
				 * then add it to the fields array
				 */
				$input_fields[ $individual_setting_key ] = [
					'type' => Types::get_type( $setting['type'] ),
					'description' => $setting['description'],
				];

			}

			self::$input_fields = apply_filters( 'graphql_setting_mutation_input_fields', $input_fields );

		}

		return ( ! empty( self::$input_fields ) ? self::$input_fields : null );

	}

	/**
	 * This prepares the media item for insertion
	 *
	 * @param array         $input            The input for the mutation from the GraphQL request
	 * @param \WP_Post_Type $post_type_object The post_type_object for the mediaItem (attachment)
	 * @param string        $mutation_name    The name of the mutation being performed (create, update, etc.)
	 * @param mixed         $file             The mediaItem (attachment) file
	 *
	 * @return array $insert_options_array
	 */
	public static function prepare_settings( $input ) {

		$insert_options_array = [];

		$settings_array = DataSource::get_allowed_settings();

		/**
		 * Loop through the $settings_array and build the insert options array
		 */
		foreach ( $settings_array as $key => $setting ) {

			/**
			 * Determine if the individual setting already has a
			 * REST API name, if not use the option name (setting).
			 * Sanitize the field name to be camelcase
			 */
			if ( ! empty( $setting['show_in_rest']['name'] ) ) {
				$individual_setting_key = lcfirst( $setting['group'] . 'Settings' . str_replace( '_', '', ucwords( $setting['show_in_rest']['name'], '_' ) ) );
			} else {
				$individual_setting_key = lcfirst( $setting['group'] . 'Settings' . str_replace( '_', '', ucwords( $key, '_' ) ) );
			}

			/**
			 * Dynamically build the individual setting and it's fields
			 * then add it to the fields array
			 */
			$insert_options_array[ $individual_setting_key ] = [
				'option' => $key,
				'group' => $setting['group'],
			];

		}

		/**
		 * Filter the $insert_options_rgs
		 *
		 * @param array         $insert_post_args The array of $input_post_args that will be passed to wp_insert_attachment
		 * @param array         $input            The data that was entered as input for the mutation
		 * @param \WP_Post_Type $post_type_object The post_type_object that the mutation is affecting
		 * @param string        $mutation_type    The type of mutation being performed (create, update, delete)
		 */
		$insert_options_args = apply_filters( 'graphql_media_item_insert_settings_args', $insert_options_array, $input );

		return $insert_options_args;
	}


}
