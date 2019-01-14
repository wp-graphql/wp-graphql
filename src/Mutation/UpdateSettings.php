<?php

namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;
use WPGraphQL\Data\DataSource;

class UpdateSettings {
    /**
     * Registers the CommentCreate mutation.
     */
    public static function register_mutation() {
        register_graphql_mutation( 'updateSettings', [
            'inputFields'         => self::get_input_fields(),
            'outputFields'        => self::get_output_fields(),
            'mutateAndGetPayload' => self::mutate_and_get_payload(),
        ] );
    }

    /**
     * Defines the mutation input field configuration.
     *
     * @return array
     */
    public static function get_input_fields() {
        $allowed_settings = DataSource::get_allowed_settings();

        $input_fields = [];

        if ( ! empty( $allowed_settings ) && empty( self::$input_fields ) ) {

            /**
             * Loop through the $allowed_settings and build fields
             * for the individual settings
             */
            foreach ( $allowed_settings as $key => $setting ) {

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
     * @return array
     */
    public static function get_output_fields() {
        $output_fields = [];

        /**
         * Get the allowed setting groups and their fields
         */
        $allowed_setting_groups = DataSource::get_allowed_settings_by_group();
        if ( ! empty( $allowed_setting_groups ) && is_array( $allowed_setting_groups ) ) {
            foreach ( $allowed_setting_groups as $group => $setting_type ) {
                $setting_type = str_replace( '_', '', strtolower( $group ) );

                $output_fields[ $setting_type . 'Settings' ] = [
                    'type'    => $setting_type . 'Settings',
                    'resolve' => function () use ( $setting_type ) {
                        return $setting_type;
                    },
                ];

            }
        }

        /**
         * Get all of the settings, regardless of group
         */
        $output_fields['allSettings'] = [
            'type'    => 'Settings',
            'resolve' => function () {
                return true;
            },
        ];

        return $output_fields;
    }

    /**
     * Defines the mutation data modification closure.
     *
     * @return callable
     */
    public static function mutate_and_get_payload() {
        return function ( $input ) {
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

            $allowed_settings = DataSource::get_allowed_settings();

            /**
             * Loop through the $allowed_settings and build the insert options array
             */
            foreach ( $allowed_settings as $key => $setting ) {

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

                /**
                 * Dynamically build the individual setting,
                 * then add it to $updatable_settings_options
                 */
                $updatable_settings_options[ $individual_setting_key ] = [
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
        };
    }
}