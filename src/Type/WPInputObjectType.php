<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\InputObjectType;

/**
 * Class WPInputObjectType
 *
 * Input types should extend this class to take advantage of the helper methods for formatting
 * and adding consistent filters.
 *
 * @package WPGraphQL\Type
 * @since 0.0.5
 */
class WPInputObjectType extends InputObjectType {

	/**
	 * WPInputObjectType constructor.
	 *
	 * @param string $name
	 * @param array $fields
	 * @param string $description
	 */
	public function __construct( $name, $fields, $description = null ) {
		$config['name'] = $name;
		$config['fields'] = self::prepare_fields( $fields, $name );
		$config['description'] = $description;
		parent::__construct( $config );
	}

	/**
	 * prepare_fields
	 *
	 * This function sorts the fields and applies a filter to allow for easily
	 * extending/modifying the shape of the Schema for the type.
	 *
	 * @param array $fields
	 * @param string $type_name
	 * @return mixed
	 * @since 0.0.5
	 */
	public static function prepare_fields( array $fields, $type_name ) {

		/**
		 * Pass the fields through a filter
		 *
		 * @param array $fields
		 *
		 * @since 0.0.5
		 */
		$fields = apply_filters( "graphql_{$type_name}_fields", $fields );

		/**
		 * Sort the fields alphabetically by key. This makes reading through docs much easier
		 * @since 0.0.2
		 */
		ksort( $fields );

		/**
		 * Return the filtered, sorted $fields
		 * @since 0.0.5
		 */
		return $fields;
	}

	/**
	 * format_enum_name
	 *
	 * This formats enum_names to be all caps with underscores for spaces/word-breaks
	 *
	 * @param $name
	 * @return string
	 * @since 0.0.5
	 */
	public static function format_enum_name( $name ) {
		return strtoupper( preg_replace( '/[^A-Za-z0-9]/i', '_', $name ) );
	}

}
