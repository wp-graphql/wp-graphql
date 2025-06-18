<?php

namespace WPGraphQL\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Utils\Utils;
use DateTime as PHPDateTime;

/**
 * Class DateTime
 *
 * The `DateTime` scalar type represents a date and time in the ISO 8601 format.
 * For example: `2020-01-01T12:30:00Z`
 *
 * @package WPGraphQL\Type\Scalar
 */
class DateTime {

	/**
	 * Serializes an internal value to include in a response.
	 *
	 * @param mixed $value
	 * @return string|null
	 * @throws Error
	 */
	public static function serialize( $value ) {
		if ( null === $value || '' === $value || '0000-00-00 00:00:00' === $value ) {
			return null;
		}

		if ( ! is_string( $value ) ) {
			throw new Error(
				\esc_html(
					\sprintf(
						/* translators: %s: The value that was passed to be serialized */
						\__( 'DateTime must be a string. Received: %s', 'wp-graphql' ),
						Utils::printSafe( $value )
					)
				)
			);
		}

		try {
			$date = new PHPDateTime( $value );
			return $date->format( 'Y-m-d\TH:i:s\Z' );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Parses an externally provided value (query variable) to use as an input.
	 *
	 * @param mixed $value
	 * @return string
	 * @throws Error
	 *
	 * NOTE: `parseValue` is a required method for all Custom Scalars in `graphql-php`.
	 */
	public static function parseValue( $value ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( ! self::validate_date_time( $value ) ) {
			throw new Error(
				\esc_html(
					\sprintf(
						/* translators: %s: The invalid DateTime value */
						\__( 'Value is not a valid DateTime: %s', 'wp-graphql' ),
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

		return self::parseValue( $valueNode->value );
	}

	/**
	 * Validate that the date is in the Y-m-d H:i:s format.
	 *
	 * @param string $date
	 * @return bool
	 */
	private static function validate_date_time( string $date ): bool {
		$d = PHPDateTime::createFromFormat( 'Y-m-d\TH:i:s\Z', $date );
		return $d && $d->format( 'Y-m-d\TH:i:s\Z' ) === $date;
	}

	/**
	 * Registers the DateTime Scalar type to the Schema.
	 *
	 * @return void
	 */
	public static function register_scalar() {
		register_graphql_scalar(
			'DateTime',
			[
				'description'  => \__( 'A date and time string in ISO 8601 format, such as `2020-01-01T12:00:00Z`.', 'wp-graphql' ),
				'serialize'    => [ self::class, 'serialize' ],
				'parseValue'   => [ self::class, 'parseValue' ],
				'parseLiteral' => [ self::class, 'parseLiteral' ],
			]
		);
	}
}