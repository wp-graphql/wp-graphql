<?php

namespace WPGraphQL\Type\Setting;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Types;

/**
 * class SettingType
 *
 * This sets up the base settingType. Custom settings that are set to "show_in_graphql" automatically
 * use the SettingType and inherit the fields that are defined here. The fields get passed through a
 * filter unique to each type, so each setting can modify it's type schema via field filters.
 *
 */
class SettingType extends WPObjectType {

	/**
	 * Holds the $fields definition for the SettingType
	 *
	 * @var array $fields
	 * @access private
	 */
	private static $fields;

	/**
	 * Holds the $setting_type definition
	 *
	 * @var string $setting_type
	 * @access private
	 */
	private static $setting_type;

	/**
	 * Holds the $setting_type_array definition which contains
	 * all of the settings for the given setting_type
	 *
	 * @var array $setting_fields
	 * @access private
	 */
	private static $setting_fields;

	/**
	 * SettingType constructor.
	 *
	 * @param string $setting_type The setting group name
	 * @access public
	 */
	public function __construct( $setting_type ) {

		/**
		 * Set the setting_type so we can use it in $fields
		 */
		self::$setting_type = $setting_type;

		/**
		 * Retrieve all of the settings that are categorized under the $setting_type
		 * and set them as the $setting_type_array for later use in building fields
		 */
		self::$setting_fields = DataSource::get_setting_group_fields( $setting_type );

		$config = [
			'name'        => ucfirst( $setting_type ) . 'Settings',
			'description' => sprintf( __( 'The %s setting type', 'wp-graphql' ), $setting_type ),
			'fields'      => self::fields( self::$setting_fields, $setting_type ),
		];

		parent::__construct( $config );

	}

	/**
	 * This defines the fields (various settings) for a given setting type
	 *
	 * @param $setting_fields
	 *
	 * @access private
	 * @return \GraphQL\Type\Definition\FieldDefinition|mixed|null
	 */
	private static function fields( $setting_fields, $group ) {

		/**
		 * Set $fields to an empty array so that we aren't storing values
		 * from another setting_type
		 */
		$fields = [];

		if ( ! empty( $setting_fields ) && is_array( $setting_fields ) ) {

			/**
			 * Loop through the $setting_type_array and build the setting with
			 * proper fields
			 */
			foreach ( $setting_fields as $key => $setting_field ) {

				/**
				 * Determine if the individual setting already has a
				 * REST API name, if not use the option name (setting).
				 * Sanitize the field name to be camelcase
				 */
				if ( ! empty( $setting_field['show_in_rest']['name'] ) ) {
					$field_key = $setting_field['show_in_rest']['name'];
				} else if ( ! empty( $setting_field['setting'] ) ) {
					$field_key = $setting_field['setting'];
				} else {
					$field_key = $key;
				}

				$field_key = lcfirst( str_replace( '_', '', ucwords( $field_key, '_' ) ) );

				if ( ! empty( $key ) && ! empty( $field_key ) ) {

					/**
					 * Dynamically build the individual setting and it's fields
					 * then add it to the fields array
					 */
					$fields[ $field_key ] = [
						'type'        => Types::get_type( $setting_field['type'] ),
						'description' => $setting_field['description'],
						'resolve'     => function( $root, $args, AppContext $context, ResolveInfo $info ) use ( $setting_field, $field_key, $key ) {

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
									$option = absint( $option );
									break;
								case 'string':
									$option = (string) $option;
									break;
								case 'boolean':
									$option = (boolean) $option;
									break;
								case 'float':
								case 'number':
									$option = (float) $option;
									break;
								default:
									$option = (string) $option;
							}

							return $option;
						},
					];

				}

			}

			/**
			 * Pass the fields through a filter to allow for hooking in and adjusting the shape
			 * of the type's schema
			 */
			self::$fields = self::prepare_fields( $fields, self::$setting_type );

		}

		return ! empty( self::$fields ) ? self::$fields : null;

	}

}
