<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\EnumType;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class WPEnumType
 *
 * EnumTypes should extend this class to have filters and sorting applied, etc.
 *
 * @package WPGraphQL\Type
 *
 * phpcs:disable -- For phpstan type hinting
 * @phpstan-import-type PartialEnumValueConfig from \GraphQL\Type\Definition\EnumType
 * @phpstan-import-type EnumValues from \GraphQL\Type\Definition\EnumType
 *
 * @phpstan-type PartialWPEnumValueConfig array{
 *   name?: string,
 *   value?: mixed,
 *   deprecationReason?: string|callable():string|null,
 *   description?: string|callable():string|null,
 *   astNode?: \GraphQL\Language\AST\EnumValueDefinitionNode|null
 * }
 * @phpstan-type WPEnumTypeConfig array{
 *  name: string,
 *  description?: string|null,
 *  values: array<string, PartialWPEnumValueConfig>,
 *  astNode?: \GraphQL\Language\AST\EnumTypeDefinitionNode|null,
 *  extensionASTNodes?: array<\GraphQL\Language\AST\EnumTypeExtensionNode>|null,
 *  kind?:'enum'|null,
 * }
 * phpcs:enable
 */
class WPEnumType extends EnumType {

	/**
	 * WPEnumType constructor.
	 *
	 * @param array<string,mixed> $config
	 * @phpstan-param WPEnumTypeConfig $config
	 */
	public function __construct( $config ) {
		$name             = ucfirst( $config['name'] );
		$config['name']   = apply_filters( 'graphql_type_name', $name, $config, $this );
		$config['values'] = self::prepare_values( $config['values'], $config['name'] );
		parent::__construct( $config );
	}

	/**
	 * Generate a safe / sanitized Enum value from a string.
	 *
	 * @param  string $value Enum value.
	 * @return string
	 */
	public static function get_safe_name( string $value ) {
		$sanitized_enum_name = graphql_format_name( $value, '_' );

		// If the sanitized name is empty, we want to return the original value so it displays in the error.
		if ( ! empty( $sanitized_enum_name ) ) {
			$value = $sanitized_enum_name;
		}

		$safe_name = strtoupper( $value );

		// Enum names must start with a letter or underscore.
		if ( ! preg_match( '#^[_a-zA-Z]#', $safe_name ) ) {
			return '_' . $safe_name;
		}

		return $safe_name;
	}

	/**
	 * This function sorts the values and applies a filter to allow for easily
	 * extending/modifying the shape of the Schema for the enum.
	 *
	 * @param array<string,PartialWPEnumValueConfig> $values
	 * @param string                                 $type_name
	 *
	 * @return EnumValues
	 * @since 0.0.5
	 */
	private static function prepare_values( $values, $type_name ) {

		// Map over the values and if the description is a callable, resolve it.
		foreach ( $values as $key => $value ) {
			$description = $value['description'] ?? null;

			if ( is_callable( $description ) ) {
				$description = $description();
			}

			$values[ $key ]['description'] = is_string( $description ) ? $description : '';
		}

		/**
		 * Filter all object fields, passing the $typename as a param
		 *
		 * This is useful when several different types need to be easily filtered at once. . .for example,
		 * if ALL types with a field of a certain name needed to be adjusted, or something to that tune
		 *
		 * @param EnumValues $values
		 * @param string     $type_name
		 */
		$values = apply_filters( 'graphql_enum_values', $values, $type_name );

		/**
		 * Pass the values through a filter
		 *
		 * Filter for lcfirst( $type_name ) was added for backward compatibility
		 *
		 * This is useful for more targeted filtering, and is applied after the general filter, to allow for
		 * more specific overrides
		 *
		 * @param EnumValues $values
		 * @param string     $type_name
		 *
		 * @since 0.0.5
		 */
		$values = apply_filters( 'graphql_' . lcfirst( $type_name ) . '_values', $values, $type_name );
		$values = apply_filters( 'graphql_' . $type_name . '_values', $values, $type_name );

		// map over the values and if the description is a callable, call it
		$values = array_map(
			static function ( $value ) {
				if ( ! is_array( $value ) ) {
					$value = [ 'value' => $value ];
				}
				return TypeRegistry::prepare_config_for_introspection( $value );
			},
			$values
		);

		/**
		 * Sort the values alphabetically by key. This makes reading through docs much easier
		 *
		 * @since 0.0.5
		 */
		ksort( $values );

		return $values;
	}
}
