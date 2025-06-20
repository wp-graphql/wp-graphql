<?php

namespace WPGraphQL\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Utils\Utils;

/**
 * Class URI
 *
 * The `URI` scalar type represents a Uniform Resource Identifier, which is a path-like string.
 * It is used for fields that should contain a relative path within the site, such as `/my-post/`.
 *
 * @package WPGraphQL\Type\Scalar
 */
class URI {

	/**
	 * Serializes an internal value to include in a response.
	 *
	 * @param mixed $value
	 * @return string|null
	 * @throws \GraphQL\Error\Error
	 */
	public static function serialize( $value ) {
		if ( ! is_string( $value ) ) {
			// Allow null values to pass through.
			if ( null === $value ) {
				return null;
			}
			throw new Error(
				\esc_html(
					\sprintf(
						/* translators: %s: The value that was passed to be serialized */
						\__( 'URI must be a string. Received: %s', 'wp-graphql' ),
						Utils::printSafe( $value )
					)
				)
			);
		}

		// Don't allow empty strings, but allow a single slash for the root.
		if ( '' === $value ) {
			return null;
		}

		// A valid URI should start with a forward slash.
		if ( 0 !== strpos( $value, '/' ) ) {
			throw new Error(
				\esc_html(
					\sprintf(
						/* translators: %s: The invalid URI value */
						\__( 'Value is not a valid URI: %s', 'wp-graphql' ),
						Utils::printSafe( $value )
					)
				)
			);
		}

		return $value;
	}

	/**
	 * Parses an externally provided value (query variable) to use as an input.
	 *
	 * @param mixed $value
	 * @return string|null
	 * @throws \GraphQL\Error\Error
	 *
	 * NOTE: `parseValue` is a required method for all Custom Scalars in `graphql-php`.
	 */
	public static function parseValue( $value ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return self::serialize( $value );
	}

	/**
	 * Parses an externally provided literal value (hardcoded in GraphQL query) to use as an input.
	 *
	 * @param \GraphQL\Language\AST\Node $valueNode
	 * @param array<string,mixed>|null   $variables
	 * @return string|null
	 * @throws \GraphQL\Error\Error
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
	 * Registers the URI Scalar type to the Schema.
	 *
	 * @return void
	 */
	public static function register_scalar() {
		register_graphql_scalar(
			'URI',
			[
				'description'  => \__( 'A Uniform Resource Identifier (URI), which can be a relative path such as `/my-post/`.', 'wp-graphql' ),
				'serialize'    => [ self::class, 'serialize' ],
				'parseValue'   => [ self::class, 'parseValue' ],
				'parseLiteral' => [ self::class, 'parseLiteral' ],
			]
		);
	}
}
