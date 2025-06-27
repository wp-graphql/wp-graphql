<?php

namespace WPGraphQL\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Utils\Utils;

/**
 * Class Slug
 *
 * The `Slug` scalar type represents a WordPress slug, which is a sanitized, URL-friendly version of a string.
 * It is used for user-friendly URLs.
 *
 * @package WPGraphQL\Type\Scalar
 */
class Slug {

	/**
	 * Coerces the value to a valid slug.
	 *
	 * @param mixed $value
	 * @return string
	 * @throws \Exception
	 */
	private static function coerce( $value ) {
		if ( ! is_string( $value ) ) {
			throw new \Exception(
				\esc_html(
					\sprintf(
						/* translators: %s: The value that was passed to be serialized */
						\__( 'Slug must be a string. Received: %s', 'wp-graphql' ),
						Utils::printSafe( $value )
					)
				)
			);
		}

		if ( sanitize_title( $value ) !== $value ) {
			throw new \Exception(
				\esc_html(
					\sprintf(
						/* translators: %s: The invalid slug value */
						\__( 'Value is not a valid slug: %s', 'wp-graphql' ),
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
	 * @return string
	 * @throws \GraphQL\Error\InvariantViolation
	 */
	public static function serialize( $value ) {
		try {
			return self::coerce( $value );
		} catch ( \Throwable $e ) {
			throw new InvariantViolation( esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Parses an externally provided value (query variable) to use as an input
	 *
	 * @param mixed $value
	 * @return string
	 * @throws \GraphQL\Error\Error
	 *
	 * NOTE: `parseValue` is a required method for all Custom Scalars in `graphql-php`.
	 */
	public static function parseValue( $value ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		try {
			return self::coerce( $value );
		} catch ( \Throwable $e ) {
			throw new Error( esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Parses an externally provided literal value (hardcoded in GraphQL query) to use as an input.
	 *
	 * @param \GraphQL\Language\AST\Node $valueNode
	 * @param array<string,mixed>|null   $variables
	 * @return string
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
	 * Registers the Slug Scalar type to the Schema
	 *
	 * @return void
	 */
	public static function register_scalar() {
		register_graphql_scalar(
			'Slug',
			[
				'description'  => \__( 'A URL-friendly version of a string, such as `my-post-title`.', 'wp-graphql' ),
				'serialize'    => [ self::class, 'serialize' ],
				'parseValue'   => [ self::class, 'parseValue' ],
				'parseLiteral' => [ self::class, 'parseLiteral' ],
			]
		);
	}
}
