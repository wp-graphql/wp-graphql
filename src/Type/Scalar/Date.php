<?php

namespace WPGraphQL\Type\Scalar;

use DateTime;
use DateTimeZone;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Utils\Utils;



/**
 * Class Date
 *
 * @package WPGraphQL\Type\Scalar
 */
class Date {

	/**
	 * Serializes an internal value to include in a response.
	 *
	 * @param mixed $value
	 * @return string|null
	 * @throws \GraphQL\Error\InvariantViolation
	 */
	public static function serialize( $value ) {
		if ( empty( $value ) || '0000-00-00 00:00:00' === $value ) {
			return null;
		}

		try {
			// Get the site's timezone
			$timezone = new DateTimeZone( get_option( 'timezone_string' ) ?: 'UTC' );

			// Create a new DateTime object with the site's timezone
			$date = new DateTime( $value, $timezone );
		} catch ( \Throwable $e ) {
			throw new InvariantViolation(
				esc_html(
					sprintf(
					/* translators: %s: The value that was passed to be serialized */
						__( 'Date cannot be serialized from a non-string, non-numeric, or non-DateTime object.', 'wp-graphql' ),
						Utils::printSafe( $value )
					)
				)
			);
		}

		// Return the date in 'Y-m-d' format.
		return $date->format( 'Y-m-d' );
	}

	/**
	 * Parses an externally provided value (query variable) to use as an input
	 *
	 * @param mixed $value The value that was passed to be parsed.
	 * @return string
	 * @throws \GraphQL\Error\Error
	 *
	 * NOTE: `parseValue` is a required method for all Custom Scalars in `graphql-php`.
	 */
	public static function parseValue( $value ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		try {
			$date = new DateTime( $value );
		} catch ( \Throwable $e ) {
			throw new Error(
				esc_html(
					sprintf(
					/* translators: %s: The value that was passed to be serialized */
						__( 'Date must be a string in a format that can be parsed by PHP\'s DateTime class. Received: %s', 'wp-graphql' ),
						Utils::printSafe( $value )
					)
				)
			);
		}

		return $date->format( 'Y-m-d' );
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
	 * Registers the Date Scalar type to the Schema
	 *
	 * @return void
	 */
	public static function register_scalar() {
		register_graphql_scalar(
			'Date',
			[
				'description'    => __( 'The `Date` scalar type represents a date, represented as a `Y-m-d` formatted string. It adheres to the `full-date` production of the RFC 3339 profile of the ISO 8601 standard.', 'wp-graphql' ),
				'serialize'      => [ self::class, 'serialize' ],
				'parseValue'     => [ self::class, 'parseValue' ],
				'parseLiteral'   => [ self::class, 'parseLiteral' ],
				'specifiedByURL' => 'https://datatracker.ietf.org/doc/html/rfc3339',
			]
		);
	}
}
