<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\EnumType;

/**
 * Class WPEnumType
 *
 * EnumTypes should extend this class to have filters and sorting applied, etc.
 *
 * @package WPGraphQL\Type
 */
class WPEnumType extends EnumType {

	/**
	 * WPInputObjectType constructor.
	 *
	 * @param string $name
	 * @param array $values
	 * @param string $description
	 */
	public function __construct( $name, $values, $description = null ) {
		$config['name'] = $name;
		$config['values'] = self::prepare_values( $values, $name );
		$config['description'] = $description;
		parent::__construct( $config );
	}

	/**
	 * prepare_values
	 *
	 * This function sorts the values and applies a filter to allow for easily
	 * extending/modifying the shape of the Schema for the enum.
	 *
	 * @param array $values
	 * @param string $type_name
	 * @return mixed
	 * @since 0.0.5
	 */
	private static function prepare_values( $values, $type_name ) {

		/**
		 * Pass the values through a filter
		 *
		 * @param array $values
		 *
		 * @since 0.0.5
		 */
		$values = apply_filters( 'graphql_' . $type_name . '_values', $values );

		/**
		 * Sort the values alphabetically by key. This makes reading through docs much easier
		 * @since 0.0.5
		 */
		ksort( $values );

		/**
		 * Return the filtered, sorted $fields
		 * @since 0.0.5
		 */
		return $values;

	}

}
