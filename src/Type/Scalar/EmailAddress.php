<?php

namespace WPGraphQL\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Utils\Utils;

/**
 * Class EmailAddress
 *
 * @package WPGraphQL\Type\Scalar
 */
class EmailAddress {

	/**
	 * Coerces the value to a valid email address.
	 *
	 * @param mixed $value
	 * @return string
	 * @throws \Exception
	 */
	private static function coerce( $value ) {
		if ( ! is_string( $value ) ) {
			throw new \Exception(
				esc_html(
					sprintf(
						/* translators: %s: The value that was passed to be serialized */
						__( 'Email address must be a string. Received: %s', 'wp-graphql' ),
						Utils::printSafe( $value )
					)
				)
			);
		}

		// Use WordPress's is_email() function to validate the email
		if ( ! is_email( $value ) ) {
			throw new \Exception(
				esc_html(
					sprintf(
						/* translators: %s: The invalid email value */
						__( 'Value is not a valid email address: %s', 'wp-graphql' ),
						Utils::printSafe( $value )
					)
				)
			);
		}

		// Use WordPress's sanitize_email() function to sanitize the email
		return sanitize_email( $value );
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
			throw new \GraphQL\Error\InvariantViolation(
				esc_html( $e->getMessage() )
			);
		}
	}

	/**
	 * Parses an externally provided literal value (hardcoded in GraphQL query) to use as an input.
	 *
	 * @param mixed $value The value that was passed to be parsed.
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
	public static function parseLiteral( $valueNode, ?array $variables = null ) { //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( ! $valueNode instanceof \GraphQL\Language\AST\StringValueNode ) {
			throw new Error(
				esc_html__( 'Email address must be a string.', 'wp-graphql' ),
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				$valueNode
			);
		}

		return self::parseValue( $valueNode->value );
	}

	/**
	 * Registers the EmailAddress Scalar type to the Schema
	 *
	 * @return void
	 */
	public static function register_scalar() {
		register_graphql_scalar(
			'EmailAddress',
			[
				'description'  => __( 'The `EmailAddress` scalar type represents a valid email address, conforming to the HTML specification and RFC 5322.', 'wp-graphql' ),
				'serialize'    => [ self::class, 'serialize' ],
				'parseValue'   => [ self::class, 'parseValue' ],
				'parseLiteral' => [ self::class, 'parseLiteral' ],
			]
		);
	}
}
