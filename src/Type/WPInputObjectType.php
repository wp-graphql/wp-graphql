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
	 * prepare_fields
	 *
	 * This function sorts the fields and applies a filter to allow for easily
	 * extending/modifying the shape of the Schema for the type.
	 *
	 * @param array $fields
	 * @param string $type_name
	 * @param array $config
	 * @return mixed
	 * @since 0.0.5
	 */
	public static function prepare_fields( array $fields, $type_name, $config = [] ) {

		/**
		 * Filter all object fields, passing the $typename as a param
		 *
		 * This is useful when several different types need to be easily filtered at once. . .for example,
		 * if ALL types with a field of a certain name needed to be adjusted, or something to that tune
		 *
		 * @param array  $fields    The array of fields for the object config
		 * @param string $type_name The name of the object type
		 */
		$fields = apply_filters( 'graphql_input_fields', $fields, $type_name, $config );

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
}
