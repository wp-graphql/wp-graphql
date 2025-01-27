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
	 * Serializes an internal value to include in a response.
	 *
	 * @param mixed $value
	 * @return string
	 * @throws \GraphQL\Error\Error
	 */
	public static function serialize( $value ) {
		// If the value isn't a string, throw an error
		if ( ! is_string( $value ) ) {
			throw new Error(
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
			throw new Error(
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
	 * Parses an externally provided value (query variable) to use as an input
	 *
	 * @param mixed $value
	 * @return string
	 * @throws \GraphQL\Error\Error
	 */
	public static function parseValue( $value ) {
		return self::serialize( $value );
	}

	/**
	 * Parses an externally provided literal value (hardcoded in GraphQL query) to use as an input.
	 *
	 * @param \GraphQL\Language\AST\Node $valueNode
	 * @param array<string,mixed>|null   $variables
	 * @return string
	 * @throws \GraphQL\Error\Error
	 */
	public static function parseLiteral( $valueNode, ?array $variables = null ) {
		// Check if the value node is a string
		if ( ! property_exists( $valueNode, 'value' ) || ! is_string( $valueNode->value ) ) {
			throw new Error(
				esc_html__( 'Email address must be a string.', 'wp-graphql' ),
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				$valueNode
			);
		}

		return self::serialize( $valueNode->value );
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
				'description'  => __( 'A field whose value conforms to the standard internet email address format as specified in HTML Spec: https://html.spec.whatwg.org/multipage/input.html#valid-e-mail-address.', 'wp-graphql' ),
				'serialize'    => [ static::class, 'serialize' ],
				'parseValue'   => [ static::class, 'parseValue' ],
				'parseLiteral' => [ static::class, 'parseLiteral' ],
			]
		);
	}
}
