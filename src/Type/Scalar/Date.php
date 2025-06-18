<?php

namespace WPGraphQL\Type\Scalar;

use DateTime;
use GraphQL\Error\Error;
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
	 */
	public static function serialize( $value ) {
		if ( empty( $value ) || '0000-00-00 00:00:00' === $value ) {
			return null;
		}

		try {
			$date = new DateTime( $value );
			return $date->format( 'Y-m-d' );
		} catch ( \Throwable $e ) {
			return null;
		}
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
		if ( ! is_string( $value ) || ! self::validate_date( $value ) ) {
			throw new Error(
				\esc_html(
					sprintf(
						/* translators: %s: The value that was passed to be serialized */
						\__( 'Date must be a string in Y-m-d format. Received: %s', 'wp-graphql' ),
						Utils::printSafe( $value )
					)
				)
			);
		}
		return $value;
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
		\register_graphql_scalar(
			'Date',
			[
				'description'  => __( 'A date string with format Y-m-d. For example: 2020-01-01.', 'wp-graphql' ),
				'serialize'    => [ self::class, 'serialize' ],
				'parseValue'   => [ self::class, 'parseValue' ],
				'parseLiteral' => [ self::class, 'parseLiteral' ],
			]
		);
	}

	/**
	 * Validate that the date is in the Y-m-d format.
	 *
	 * @param string $date
	 */
	private static function validate_date( string $date ): bool {
		$d = DateTime::createFromFormat( 'Y-m-d', $date );
		return $d && $d->format( 'Y-m-d' ) === $date;
	}
}
