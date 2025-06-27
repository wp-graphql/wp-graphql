<?php

namespace WPGraphQL\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Utils\Utils;

/**
 * Class UrlPath
 *
 * The `UrlPath` scalar type represents a Uniform Resource Identifier, which is a path-like string.
 * It is used for fields that should contain a relative path within the site, such as `/my-post/`.
 *
 * @package WPGraphQL\Type\Scalar
 */
class UrlPath {

	/**
	 * Coerces the value to a valid UrlPath.
	 *
	 * @param mixed $value
	 * @return string|null
	 * @throws \Exception
	 */
	private static function coerce( $value ) {
		if ( ! is_string( $value ) ) {
			// Allow null values to pass through.
			if ( null === $value ) {
				return null;
			}
			throw new \Exception(
				\esc_html(
					\sprintf(
						/* translators: %s: The value that was passed to be serialized */
						\__( 'UrlPath must be a string. Received: %s', 'wp-graphql' ),
						Utils::printSafe( $value )
					)
				)
			);
		}

		// Don't allow empty strings, but allow a single slash for the root.
		if ( '' === $value ) {
			return null;
		}

		// A valid UrlPath should start with a forward slash.
		if ( 0 !== strpos( $value, '/' ) ) {
			throw new \Exception(
				\esc_html(
					\sprintf(
						/* translators: %s: The invalid UrlPath value */
						\__( 'Value is not a valid UrlPath: %s', 'wp-graphql' ),
						Utils::printSafe( $value )
					)
				)
			);
		}

		return $value;
	}

	/**
	 * Serializes an internal value to include in a response.
	 *
	 * @param mixed $value
	 * @return string|null
	 * @throws \GraphQL\Error\InvariantViolation
	 */
	public static function serialize( $value ) {
		try {
			return self::coerce( $value );
		} catch ( \Throwable $e ) {
			throw new InvariantViolation( \esc_html( $e->getMessage() ) );
		}
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
		try {
			return self::coerce( $value );
		} catch ( \Throwable $e ) {
			throw new Error( \esc_html( $e->getMessage() ) );
		}
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

		return self::parseValue( $valueNode->value );
	}

	/**
	 * Registers the UrlPath Scalar type to the Schema.
	 *
	 * @return void
	 */
	public static function register_scalar() {
		register_graphql_scalar(
			'UrlPath',
			[
				'description'  => \__( 'A relative path, such as `/my-post/`', 'wp-graphql' ),
				'serialize'    => [ self::class, 'serialize' ],
				'parseValue'   => [ self::class, 'parseValue' ],
				'parseLiteral' => [ self::class, 'parseLiteral' ],
			]
		);
	}
}
