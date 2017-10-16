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

		// This will dump the array containing all of the keys for the $setting_type (general, discussion, etc.)
		// var_dump( self::$setting_type_array );

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
		 * If no fields have been defined for this type already,
		 * make sure the $fields var is an empty array
		 */
		if ( null === self::$fields ) {
			self::$fields = [];
		}

		/**
		 * Loop through the $setting_type_array and build the setting with
		 * proper fields
		 */
		foreach ( $setting_type_array as $key => $value ) {

			$option_name = $value['setting'];

			/**
			 * Define the setting array for storing the setting data
			 * we're going to expose
			 */
			$setting = [];

			/**
			 * Determine if the individual setting already has a
			 * REST API name, if not use the option name. Then,
			 * set the setting_type for our field name
			 */
			if ( ! empty( $value['show_in_rest']['name'] ) ) {
				$setting['name'] = $value['show_in_rest']['name'];
				$individual_setting_key = $setting['name'];
			} else {
				$setting['name'] = str_replace( '_', '', strtolower( $value['setting'] ) );
				$individual_setting_key = $setting['name'];
			}

			/**
			 * Dynamically build the individual setting and it's fields
			 * then add it to the fields array
			 */
			self::$fields[ $individual_setting_key ] = [
				'type' => DataSource::resolve_setting_scalar_type( $value['type'] ),
				'description' => $value['description'],
				'resolve' => function( $option_name ) {

					// This is returning null
					var_dump( $option_name );
					return get_option( $value['setting'] );
				},
			];

		}

		return ! empty( self::$fields ) ? self::$fields : null;

	}

}
