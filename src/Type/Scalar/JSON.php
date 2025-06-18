<?php

namespace WPGraphQL\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\NullValueNode;

/**
 * Class JSON
 *
 * The `JSON` scalar type represents JSON values as specified by ECMA-404.
 *
 * @package WPGraphQL\Type\Scalar
 */
class JSON {

	/**
	 * Serializes an internal value to include in a response.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public static function serialize( $value ) {
		return $value;
	}

	/**
	 * Parses an externally provided value (query variable) to use as an input.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	public static function parseValue( $value ) {
		return $value;
	}

	/**
	 * Parses an externally provided literal value (hardcoded in GraphQL query) to use as an input.
	 *
	 * @param Node                $valueNode
	 * @param array<string,mixed>|null $variables
	 * @return mixed
	 */
	public static function parseLiteral( $valueNode, ?array $variables = null ) {
		return self::astToPhp( $valueNode );
	}

	/**
	 * Converts an AST node to a PHP value.
	 *
	 * @param Node $ast
	 * @return mixed
	 */
	private static function astToPhp( $ast ) {
		if ( $ast instanceof ObjectValueNode ) {
			$values = [];
			foreach ( $ast->fields as $field ) {
				$values[ $field->name->value ] = self::astToPhp( $field->value );
			}
			return $values;
		}

		if ( $ast instanceof ListValueNode ) {
			$values = [];
			foreach ( $ast->values as $value ) {
				$values[] = self::astToPhp( $value );
			}
			return $values;
		}

		if ( $ast instanceof NullValueNode ) {
			return null;
		}

		if ( property_exists( $ast, 'value' ) ) {
			return $ast->value;
		}

		return null;
	}

	/**
	 * Registers the JSON Scalar type to the Schema.
	 *
	 * @return void
	 */
	public static function register_scalar() {
		register_graphql_scalar(
			'JSON',
			[
				'description'  => \__( 'The `JSON` scalar type represents JSON values as specified by ECMA-404. It is useful for returning arbitrary data that is not predefined in the schema.', 'wp-graphql' ),
				'serialize'    => [ self::class, 'serialize' ],
				'parseValue'   => [ self::class, 'parseValue' ],
				'parseLiteral' => [ self::class, 'parseLiteral' ],
			]
		);
	}
}