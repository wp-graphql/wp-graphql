<?php

namespace WPGraphQL\Type\Setting\Mutation;

use WPGraphQL\Types;

/**
 * Class SettingMutation
 *
 * @package WPGraphQL\Type\Setting
 */
class SettingMutation {

	/**
	 * Holds the input fields configuration
	 */
	private static $input_fields;

	/**
	 * Holds the $setting_type definition
	 */
	private static $setting_type;

	/**
	 * Holds the $setting_type_array definition which contains
	 * all of the settings for the given setting_type
	 */
	private static $setting_type_array;

	/**
	 * @param $setting_type The group setting type
	 *
	 * @return mixed|array|null $input_fields
	 */
	public static function input_fields( $setting_type ) {

		if ( ! empty( $setting_type ) ) {

			$input_fields = [];

			/**
			 * Retrieve all of the settings that are categorized under the $setting_type
			 * and set them as the $setting_type_array for later use in building input fields
			 */
			self::$setting_type_array = DataSource::get_setting_type_array( $setting_type );

			/**
			 * Loop through the $setting_type_array and build the setting with
			 * proper fields
			 */
			foreach ( $setting_type_array as $key => $setting ) {

				/**
				 * Determine if the individual setting already has a
				 * REST API name, if not use the option name (setting).
				 * Sanitize the field name to be camelcase
				 */
				if ( ! empty( $setting['show_in_rest']['name'] ) ) {
					$individual_setting_key = lcfirst( str_replace( '_', '', ucwords( $setting['show_in_rest']['name'], '_' ) ) );
				} else {
					$individual_setting_key = lcfirst( str_replace( '_', '', ucwords( $setting['setting'], '_' ) ) );
				}

				/**
				 * Only add the field to the root query field if show_in_graphql is true
				 * and show_in_rest is true or an array of REST args exists
				 */
				if ( is_array( $setting['show_in_rest'] ) || true === $setting['show_in_rest'] && true === $setting['show_in_graphql'] ) {
					/**
					 * Dynamically build the individual setting and it's fields
					 * then add it to the fields array
					 */
					$input_fields[ $individual_setting_key ] = [
						'type' => DataSource::resolve_setting_scalar_type( $setting['type'] ),
						'description' => $setting['description'],
					];

				}

			}

			self::$input_fields = apply_filters( 'graphql_setting_mutation_input_fields', $input_fields );

		}

		return ( ! empty( self::$input_fields ) ) ? self::input_fields : null;

	}

	/**
	 * This prepares the media item for insertion
	 *
	 * @param array         $input            The input for the mutation from the GraphQL request
	 * @param \WP_Post_Type $post_type_object The post_type_object for the mediaItem (attachment)
	 * @param string        $mutation_name    The name of the mutation being performed (create, update, etc.)
	 * @param mixed         $file             The mediaItem (attachment) file
	 *
	 * @return array $media_item_args
	 */
	public static function prepare_settings( $input, $setting_type, $mutation_name, $file ) {

		/**
		 * Filter the $insert_post_args
		 *
		 * @param array         $insert_post_args The array of $input_post_args that will be passed to wp_insert_attachment
		 * @param array         $input            The data that was entered as input for the mutation
		 * @param \WP_Post_Type $post_type_object The post_type_object that the mutation is affecting
		 * @param string        $mutation_type    The type of mutation being performed (create, update, delete)
		 */
		$insert_setting_args = apply_filters( 'graphql_media_item_insert_setting_args', $insert_setting_args, $input, $mutation_name );


		return $input;
	}


}
