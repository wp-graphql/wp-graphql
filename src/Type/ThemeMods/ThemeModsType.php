<?php

namespace WPGraphQL\Type\ThemeMods;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Types;
use WPGraphQL\Type\ThemeMods\ThemeModsFields;

/**
 * Class ThemeModsType
 *
 * This sets up the theme modification type
 *
 * @since 0.0.32
 * @package WPGraphQL\Type\ThemeMods
 */
class ThemeModsType extends WPObjectType {

	/**
	 * Holds the type name
	 *
	 * @var string $type_name
	 */
	private static $type_name;

	/**
	 * Holds the $fields definition for the SettingsType
	 *
	 * @var array $fields
	 * @access private
	 */
	private static $fields;

	/**
	 * ThemeModType constructor.
	 *
	 * @access public
	 */
	public function __construct() {
		
		self::$type_name = 'ThemeMods';

		$config = [
			'name'        => self::$type_name,
			'fields'      => self::fields(),
			'description' => __( 'All of registered theme modifications', 'wp-graphql-extra-options' ),
		];

    parent::__construct( $config );
    
  }

  /**
	 * This defines the fields for the ThemeMods type
	 *
	 * @param $mods
	 *
	 * @access private
	 * @return \GraphQL\Type\Definition\FieldDefinition|mixed|null
	 */
	private static function fields() {

		if (null === self::$fields) {
			self::$fields = function() {
				
				/**
				 * Get theme_mod_data
				 */
				$theme_mods_data = DataSource::get_theme_mods_data();
				
				/**
				 * Loop through data and resolve field definition
				 */
				$fields = [];
				foreach( $theme_mods_data as $mod_name => $mod_data ) {

					/**
					 * Format mod name to a WPGraphQL-friendly name
					 */
					$field_key = lcfirst( str_replace( '_', '', ucwords( $mod_name, '_' ) ) );
					
					/**
					 * Dynamically build the individual setting and it's fields
					 * then add it to $fields
					 */
					$field = ThemeModsFields::$mod_name( $mod_data );
					
					if (false !== $field)	$fields[ $field_key ] = $field;

				}

				/**
				 * Prepare and return field definitions
				 */
				return self::prepare_fields( $fields, self::$type_name );;
			};
		}

    return ! empty( self::$fields ) ? self::$fields : null;

  }

}

