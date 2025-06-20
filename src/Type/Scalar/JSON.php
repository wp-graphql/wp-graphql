<?php

namespace WPGraphQL\Type\Scalar;

use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\ObjectValueNode;

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
	public static function parseValue( $value ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return $value;
	}

	/**
	 * Parses an externally provided literal value (hardcoded in GraphQL query) to use as an input.
	 *
	 * @param \GraphQL\Language\AST\Node $valueNode
	 * @param array<string,mixed>|null   $variables
	 * @return mixed
	 */
	public static function parseLiteral( $valueNode, ?array $variables = null ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return self::ast_to_php( $valueNode );
	}

	/**
	 * Converts an AST node to a PHP value.
	 *
	 * @param \GraphQL\Language\AST\Node $ast
	 * @return mixed
	 */
	private static function ast_to_php( $ast ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( $ast instanceof ObjectValueNode ) {
			$values = [];
			foreach ( $ast->fields as $field ) {
				$values[ $field->name->value ] = self::ast_to_php( $field->value );
			}
			return $values;
		}

		if ( $ast instanceof ListValueNode ) {
			$values = [];
			foreach ( $ast->values as $value ) {
				$values[] = self::ast_to_php( $value );
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
