<?php
namespace WPGraphQL\Type\Setting;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;

use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Types;


/**
 * class SettingType
 *
 * This sets up the base SettingType. Custom settings that are set to "show_in_graphql" automatically
 * use the SettingType and inherit the fields that are defined here. The fields get passed through a
 * filter unique to each type, so each setting can modify it's type schema via field filters.
 *
 */
class SettingType extends WPObjectType {

	/**
	 * Holds the $fields definition for the SettingType
	 *
	 * @var $fields
	 */
	private static $fields;

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
	 * SettingType constructor.
	 *
	 * @param string $setting_type The setting group name
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
		self::$setting_type_array = DataSource::get_setting_type_array( $setting_type );

		$config = [
			'name' => $setting_type,
			'description' => sprintf( __( 'The %s setting type', 'wp-graphql' ), $setting_type ),
			'fields' => self::fields( self::$setting_type_array ),
		];

		parent::__construct( $config );

	}

	/**
	 * This defines the fields (various settings) for a given setting type
	 *
	 * @param $setting_type_array
	 * @access private
	 * @return \GraphQL\Type\Definition\FieldDefinition|mixed|null
	 */
	private static function fields( $setting_type_array ) {

		/**
		 * Set $fields to an empty array so that we aren't storing values
		 * from another setting_type
		 */
		$fields = [];

		/**
		 * Loop through the $setting_type_array and build the setting with
		 * proper fields
		 */
		foreach ( $setting_type_array as $key => $value ) {

			/**
			 * Determine if the individual setting already has a
			 * REST API name, if not use the option name. Then,
			 * set the setting_type for our field name
			 */
			if ( ! empty( $value['show_in_rest']['name'] ) ) {
				$individual_setting_key = lcfirst( str_replace( '_', '', ucwords( $value['show_in_rest']['name'], '_' ) ) );
			} else {
				$individual_setting_key = lcfirst( str_replace( '_', '', ucwords( $value['setting'], '_' ) ) );
			}

			/**
			 * Dynamically build the individual setting and it's fields
			 * then add it to the fields array
			 */
			$fields[ $individual_setting_key ] = [
				'type' => DataSource::resolve_setting_scalar_type( $value['type'] ),
				'description' => $value['description'],
				'resolve' => function() use ( $value ) {
					return get_option( $value['setting'] );
				},
			];

		}

		/**
		 * Pass the fields through a filter to allow for hooking in and adjusting the shape
		 * of the type's schema
		 */
		self::$fields = self::prepare_fields( $fields, self::$setting_type );

		return ! empty( self::$fields ) ? self::$fields : null;

	}

}
