<?php

namespace WPGraphQL\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Utils\Utils;

/**
 * Class NonEmptyString
 *
 * The `NonEmptyString` scalar type represents a string that cannot be empty.
 * It is used to enforce that a string field contains at least one non-whitespace character.
 *
 * @package WPGraphQL\Type\Scalar
 */
class NonEmptyString {

	/**
	 * Serializes an internal value to include in a response.
	 *
	 * @param mixed $value
	 * @return string
	 * @throws Error
	 */
	public static function serialize( $value ) {
		if ( ! is_string( $value ) ) {
			throw new Error(
				\esc_html(
					sprintf(
						/* translators: %s: The value that was passed to be serialized */
						\__( 'NonEmptyString must be a string. Received: %s', 'wp-graphql' ),
						Utils::printSafe( $value )
					)
				)
			);
		}

		if ( '' === trim( $value ) ) {
			throw new Error( \__( 'NonEmptyString cannot be empty.', 'wp-graphql' ) );
		}

		return $value;
	}

	/**
	 * Parses an externally provided value (query variable) to use as an input
	 *
	 * @param mixed $value
	 * @return string
	 * @throws Error
	 *
	 * NOTE: `parseValue` is a required method for all Custom Scalars in `graphql-php`.
	 */
	public static function parseValue( $value ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return self::serialize( $value );
	}

	/**
	 * Parses an externally provided literal value (hardcoded in GraphQL query) to use as an input.
	 *
	 * @param Node                $valueNode
	 * @param array<string,mixed>|null $variables
	 * @return string
	 * @throws Error
	 *
	 * NOTE: `parseLiteral` is a required method for all Custom Scalars in `graphql-php`.
	 */
	public static function parseLiteral( $valueNode, ?array $variables = null ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( ! $valueNode instanceof StringValueNode ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Error( 'Query error: Can only parse strings got: ' . $valueNode->kind, [ $valueNode ] );
		}

		return self::serialize( $valueNode->value );
	}

	/**
	 * Registers the NonEmptyString Scalar type to the Schema
	 *
	 * @return void
	 */
	public static function register_scalar() {
		register_graphql_scalar(
			'NonEmptyString',
			[
				'description'  => \__( 'The `NonEmptyString` scalar type represents a string that cannot be empty.', 'wp-graphql' ),
				'serialize'    => [ self::class, 'serialize' ],
				'parseValue'   => [ self::class, 'parseValue' ],
				'parseLiteral' => [ self::class, 'parseLiteral' ],
			]
		);
	}
}