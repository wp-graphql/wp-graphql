<?php

namespace WPGraphQL\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Utils\Utils;

/**
 * Class JSON
 *
 * The `JSON` scalar type represents JSON values as specified by ECMA-404.
 *
 * @package WPGraphQL\Type\Scalar
 */
class JSON {

	/**
	 * Serializes an internal value to include in a response. The internal value is expected to
	 * be a PHP value that can be encoded as JSON. The result is a JSON-encoded string.
	 *
	 * @param mixed $value
	 * @throws \GraphQL\Error\Error If the value cannot be encoded as JSON.
	 */
	public static function serialize( $value ): string {
		$encoded = wp_json_encode( $value );
		if ( false === $encoded ) {
			throw new Error(
				esc_html(
					sprintf(
						/* translators: %s: The error message from json_last_error_msg() */
						__( 'Could not serialize value to JSON: %s', 'wp-graphql' ),
						json_last_error_msg()
					)
				)
			);
		}
		return $encoded;
	}

	/**
	 * Parses an externally provided value (query variable) to use as an input.
	 * The external value is expected to be a JSON-encoded string.
	 *
	 * @param mixed $value
	 * @return mixed
	 * @throws \GraphQL\Error\Error If the value is not a string or not a valid JSON-encoded string.
	 */
	public static function parseValue( $value ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( ! is_string( $value ) ) {
			// Using Utils::printSafe instead of gettype to avoid potential issues with objects that have a __toString method.
			throw new Error(
				esc_html(
					sprintf(
						/* translators: %s: The type of the value that was passed to be serialized. */
						__( 'JSON scalar expects a string, but got: %s', 'wp-graphql' ),
						Utils::printSafe( $value )
					)
				)
			);
		}
		$decoded = json_decode( $value, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new Error(
				esc_html(
					sprintf(
						/* translators: %s: The error message from json_last_error_msg() */
						__( 'Invalid JSON string provided: %s', 'wp-graphql' ),
						json_last_error_msg()
					)
				)
			);
		}
		return $decoded;
	}

	/**
	 * Parses an externally provided literal value (hardcoded in GraphQL query) to use as an input.
	 *
	 * The literal can be a JSON-encoded string or a GraphQL literal.
	 *
	 * @param \GraphQL\Language\AST\Node $valueNode
	 * @param array<string,mixed>|null   $variables
	 * @return mixed
	 */
	public static function parseLiteral( $valueNode, ?array $variables = null ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		// If the literal is a string, it must be a JSON-encoded string.
		if ( $valueNode instanceof StringValueNode ) {
			return self::parseValue( $valueNode->value );
		}

		// For other literals like ObjectValueNode, ListValueNode, we can convert them to a PHP value.
		// These are not encoded as JSON strings in the query, but as GraphQL literals.
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

		if ( $ast instanceof IntValueNode ) {
			return (int) $ast->value;
		}

		if ( $ast instanceof FloatValueNode ) {
			return (float) $ast->value;
		}

		// The remaining types are scalar values (String, Boolean, Enum), which all have a "value" property.
		if ( $ast instanceof StringValueNode || $ast instanceof BooleanValueNode || $ast instanceof EnumValueNode ) {
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
				'description'  => __( 'The `JSON` scalar type represents JSON data, represented as a JSON-encoded string. It is useful for returning arbitrary data that is not predefined in the schema. When used as an input, the value must be a JSON-encoded string.', 'wp-graphql' ),
				'serialize'    => [ self::class, 'serialize' ],
				'parseValue'   => [ self::class, 'parseValue' ],
				'parseLiteral' => [ self::class, 'parseLiteral' ],
			]
		);
	}
}
