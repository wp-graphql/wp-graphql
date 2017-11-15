<?php

namespace WPGraphQL\Type\Settings\Mutation;

use GraphQL\Error\UserError;
use GraphQL\Language\AST\Type;
use GraphQLRelay\Relay;
use WPGraphQL\Types;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Type\Setting\SettingQuery;
use WPGraphQL\Type\Settings\SettingsQuery;

/**
 * Class SettingUpdate
 *
 * @package WPGraphQL\Type\Setting\Mutation
 */
class SettingsUpdate {

	/**
	 * Stores the setting mutation field definition
	 *
	 * @var array $mutation
	 */
	private static $mutation;

	/**
	 * Define the update mutation for various setting types
	 *
	 * @return array|mixed
	 */
	public static function mutate() {

		if ( empty( self::$mutation ) ) {

			/**
			 * Set the name of the mutation being performed
			 */
			$mutation_name = 'UpdateSettings';

			self::$mutation = Relay::mutationWithClientMutationId( [
				'name'                => $mutation_name,
				'description'         => __( 'Update any of the various settings.', 'wp-graphql' ),
				'inputFields'         => SettingsMutation::input_fields(),
				'outputFields'        => self::output_fields(),
				'mutateAndGetPayload' => function ( $input ) {
					/**
					 * Check that the user can manage setting options
					 */
					if ( ! current_user_can( 'manage_options' ) ) {
						throw new UserError( __( 'Sorry, you are not allowed to edit settings as this user.', 'wp-graphql' ) );
					}

					$setting_options = SettingsMutation::prepare_settings( $input );

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
						if ( array_key_exists( $key, $setting_options ) ) {
							update_option( $setting_options[ $key ]['option'], $value );
						}

					}

				},
			] );

		}

		return ! empty( self::$mutation ) ? self::$mutation : null;

	}

	/**
	 * Build the output of the UpdateSettings mutation.
	 * This will build a combination of the setting and settings queries
	 * so that the user can query the returned data by setting group or field
	 *
	 * @access protected
	 *
	 * @return array $output_fields
	 */
	protected static function output_fields() {

		$output_fields = [];

		/**
		 * Get the allowed setting groups and their fields
		 */
		$allowed_setting_types = DataSource::get_allowed_settings_by_group();
		if ( ! empty( $allowed_setting_types ) && is_array( $allowed_setting_types ) ) {
			foreach ( $allowed_setting_types as $group => $setting_type ) {
				$setting_type = str_replace('_', '', strtolower( $group ) );
				$output_fields[ $setting_type . 'Settings' ] = SettingQuery::root_query( $group, $setting_type );
			}
		}

		/**
		 * Get all of the settings, regardless of group
		 */
		$output_fields['allSettings'] = SettingsQuery::root_query();

		return $output_fields;

	}

}
