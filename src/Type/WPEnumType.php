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

final class WPStatusEnumType {

	/**
	 * Gets built in post status as WPGraphQL enum
	 * 
	 * @return array
	 */
	public static function get_status_enum_values() {

		$post_stati = get_post_stati();

		$post_status_enum_values = [
			'name'  => 'PUBLISH',
			'value' => 'publish',
		];

		if ( ! empty( $post_stati ) && is_array( $post_stati ) ) {
			/**
			 * Reset the array
			 */
			$post_status_enum_values = [];
			/**
			 * Loop through the post_stati
			 */
			foreach ( $post_stati as $status ) {
				$post_status_enum_values[ WPEnumType::get_safe_name( $status ) ] = [
					'description' => sprintf( __( 'Objects with the %1$s status', 'wp-graphql' ), $status ),
					'value'       => $status,
				];
			}
		}

		return $post_status_enum_values;
	}

	/**
	 * Registers a status enum for a given type
	 * 
	 * @param string $type_name the GraphQL type to be registered
	 */
	public static function register_status_enum_type( $type_name ) {

		$values = self::get_status_enum_values();

		$uc_type_name = ucfirst( $type_name );
		$post_status_enum_values = apply_filters( "graphql_{$uc_type_name}_status_enum", $values );

		register_graphql_enum_type( "{$type_name}StatusEnum", [
			'description' => __( "The status of the $type_name.", 'wp-graphql' ),
			'values'      => $values,
		] );
	}

}
