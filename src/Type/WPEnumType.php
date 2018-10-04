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
	 * @param array $config
	 */
	public function __construct( $config ) {
		$config['name'] = ucfirst( $config['name'] );
		$config['values'] = self::prepare_values( $config['values'], $config['name'] );
		parent::__construct( $config );
	}

	/**
	 * Generate a safe / sanitized name from a menu location slug.
	 *
	 * @param  string $value Enum value.
	 * @return string
	 */
	public static function get_safe_name( $value ) {
		$safe_name = strtoupper( preg_replace( '#[^A-z0-9]#', '_', $value ) );

		// Enum names must start with a letter or underscore.
		if ( ! preg_match( '#^[_a-zA-Z]#', $value ) ) {
			return '_' . $safe_name;
		}

		return $safe_name;
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
		 * lcfirst( $type_name ) filter was added for backward compatibility
		 *
		 * @param array $values
		 *
		 * @since 0.0.5
		 */
		$values = apply_filters( 'graphql_' . lcfirst( $type_name ) . '_values', $values );
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
