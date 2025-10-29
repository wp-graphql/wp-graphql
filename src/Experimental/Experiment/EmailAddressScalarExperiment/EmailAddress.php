<?php

namespace WPGraphQL\Experimental\Experiment\EmailAddressScalarExperiment;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Utils\Utils;

/**
 * Class EmailAddress
 *
 * @package WPGraphQL\Experimental\Experiment\EmailAddressScalarExperiment
 */
class EmailAddress {

	/**
	 * Serializes an internal value to include in a response.
	 *
	 * @param mixed $value
	 * @return string
	 * @throws \GraphQL\Error\InvariantViolation
	 */
	public static function serialize( $value ) {
		// If the value isn't a string, throw an error
		if ( ! is_string( $value ) ) {
			throw new InvariantViolation(
				esc_html(
					sprintf(
						/* translators: %s: The value that was passed to be serialized */
						\__( 'Email address must be a string. Received: %s', 'wp-graphql' ),
						Utils::printSafe( $value )
					)
				)
			);
		}

		// Use WordPress's is_email() function to validate the email
		if ( ! \is_email( $value ) ) {
			throw new InvariantViolation(
				esc_html(
					sprintf(
						/* translators: %s: The invalid email value */
						\__( 'Value is not a valid email address: %s', 'wp-graphql' ),
						Utils::printSafe( $value )
					)
				)
			);
		}

		// Use WordPress's sanitize_email() function to sanitize the email
		return \sanitize_email( $value );
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
		// If the value isn't a string, throw an error
		if ( ! is_string( $value ) ) {
			throw new Error(
				esc_html(
					sprintf(
						/* translators: %s: The value that was passed to be serialized */
						\__( 'Email address must be a string. Received: %s', 'wp-graphql' ),
						Utils::printSafe( $value )
					)
				)
			);
		}

		// Use WordPress's is_email() function to validate the email
		if ( ! \is_email( $value ) ) {
			throw new Error(
				esc_html(
					sprintf(
						/* translators: %s: The invalid email value */
						\__( 'Value is not a valid email address: %s', 'wp-graphql' ),
						Utils::printSafe( $value )
					)
				)
			);
		}

		return \sanitize_email( $value );
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
	public static function parseLiteral( Node $valueNode, ?array $variables = null ) { //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		// Check if the value node is a string
		if ( ! $valueNode instanceof StringValueNode ) {
			throw new Error(
				esc_html(
					sprintf(
						/* translators: %s: The value that was passed to be parsed */
						\__( 'Email address must be a string. Received: %s', 'wp-graphql' ),
						! empty( $valueNode->value ) ? Utils::printSafe( $valueNode->value ) : 'unknown'
					)
				),
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				[ $valueNode ]
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
				'description'    => \__( 'The `EmailAddress` scalar type represents a valid email address, conforming to the HTML specification and RFC 5322.', 'wp-graphql' ),
				'serialize'      => [ self::class, 'serialize' ],
				'parseValue'     => [ self::class, 'parseValue' ],
				'parseLiteral'   => [ self::class, 'parseLiteral' ],
				'specifiedByURL' => 'https://html.spec.whatwg.org/multipage/input.html#valid-e-mail-address',
			]
		);
	}
}
