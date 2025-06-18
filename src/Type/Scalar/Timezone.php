<?php

namespace WPGraphQL\Type\Scalar;

use DateTimeZone;
use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Utils\Utils;

/**
 * Class Timezone
 *
 * The `Timezone` scalar type represents a timezone identifier, such as `America/New_York`.
 *
 * @package WPGraphQL\Type\Scalar
 */
class Timezone {

	/**
	 * @var array<string>|null
	 */
	private static $valid_timezones;

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
		if ( ! is_string( $value ) || ! self::is_valid_timezone( $value ) ) {
			throw new Error(
				\esc_html(
					\sprintf(
						/* translators: %s: The invalid Timezone value */
						\__( 'Value is not a valid Timezone: %s', 'wp-graphql' ),
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
	 * Validate that the timezone is a valid PHP timezone.
	 *
	 * @param string $timezone
	 */
	private static function is_valid_timezone( string $timezone ): bool {
		if ( null === self::$valid_timezones ) {
			self::$valid_timezones = DateTimeZone::listIdentifiers();
		}
		return in_array( $timezone, self::$valid_timezones, true );
	}

	/**
	 * Registers the Timezone Scalar type to the Schema.
	 *
	 * @return void
	 */
	public static function register_scalar() {
		register_graphql_scalar(
			'Timezone',
			[
				'description'  => \__( 'A timezone identifier from the IANA Time Zone Database, such as `America/New_York` or `UTC`.', 'wp-graphql' ),
				'serialize'    => [ self::class, 'serialize' ],
				'parseValue'   => [ self::class, 'parseValue' ],
				'parseLiteral' => [ self::class, 'parseLiteral' ],
			]
		);
	}
}
