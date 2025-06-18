<?php

namespace WPGraphQL\Type\Scalar;

use DateTime as PHPDateTime;
use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Utils\Utils;

/**
 * Class Time
 *
 * The `Time` scalar type represents a time in the `HH:MM:SS` format.
 *
 * @package WPGraphQL\Type\Scalar
 */
class Time {

	/**
	 * Serializes an internal value to include in a response.
	 *
	 * @param mixed $value
	 * @return string|null
	 */
	public static function serialize( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		return self::parseValue( $value );
	}

	/**
	 * Parses an externally provided value (query variable) to use as an input.
	 *
	 * @param mixed $value
	 * @return string
	 * @throws \GraphQL\Error\Error
	 */
	public static function parseValue( $value ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( ! is_string( $value ) || ! self::is_valid_time( $value ) ) {
			throw new Error(
				\esc_html(
					\sprintf(
						/* translators: %s: The invalid Time value */
						\__( 'Value is not a valid Time: %s', 'wp-graphql' ),
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
	 */
	public static function parseLiteral( $valueNode, ?array $variables = null ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( ! $valueNode instanceof StringValueNode ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Error( 'Query error: Can only parse strings got: ' . $valueNode->kind, [ $valueNode ] );
		}

		return self::parseValue( $valueNode->value );
	}

	/**
	 * Validate that the time is in the H:i:s format.
	 *
	 * @param string $time
	 */
	private static function is_valid_time( string $time ): bool {
		$d = PHPDateTime::createFromFormat( 'H:i:s', $time );
		return $d && $d->format( 'H:i:s' ) === $time;
	}

	/**
	 * Registers the Time Scalar type to the Schema.
	 *
	 * @return void
	 */
	public static function register_scalar() {
		register_graphql_scalar(
			'Time',
			[
				'description'  => \__( 'A time string in `HH:MM:SS` format, such as `12:30:00`.', 'wp-graphql' ),
				'serialize'    => [ self::class, 'serialize' ],
				'parseValue'   => [ self::class, 'parseValue' ],
				'parseLiteral' => [ self::class, 'parseLiteral' ],
			]
		);
	}
}
