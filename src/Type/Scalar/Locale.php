<?php

namespace WPGraphQL\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Utils\Utils;

/**
 * Class Locale
 *
 * The `Locale` scalar type represents a WordPress locale identifier, such as `en_US`.
 *
 * @package WPGraphQL\Type\Scalar
 */
class Locale {

	/**
	 * @var array<string>|null
	 */
	private static $valid_locales;

	/**
	 * Coerces the value to a valid locale.
	 *
	 * @param mixed $value
	 * @return string
	 * @throws \Exception
	 */
	private static function coerce( $value ) {
		if ( ! is_string( $value ) || ! self::is_valid_locale( $value ) ) {
			throw new \Exception(
				\esc_html(
					\sprintf(
						/* translators: %s: The invalid Locale value */
						\__( 'Value is not a valid Locale: %s', 'wp-graphql' ),
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
		if ( null === $value || '' === $value ) {
			return null;
		}

		try {
			return self::coerce( $value );
		} catch ( \Throwable $e ) {
			throw new \GraphQL\Error\InvariantViolation( esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Parses an externally provided value (query variable) to use as an input.
	 *
	 * @param mixed $value
	 * @return string
	 * @throws \GraphQL\Error\Error
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
	 */
	public static function parseLiteral( $valueNode, ?array $variables = null ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( ! $valueNode instanceof StringValueNode ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Error( 'Query error: Can only parse strings got: ' . $valueNode->kind, [ $valueNode ] );
		}

		return self::parseValue( $valueNode->value );
	}

	/**
	 * Validate that the locale is a valid WordPress locale.
	 *
	 * @param string $locale
	 */
	private static function is_valid_locale( string $locale ): bool {
		if ( null === self::$valid_locales ) {
			// A list of all available languages.
			// See https://developer.wordpress.org/reference/functions/get_available_languages/
			$available_languages = get_available_languages();

			// Add 'en_US' to the list of available languages, as it's the default and might not be in the languages folder.
			if ( ! in_array( 'en_US', $available_languages, true ) ) {
				$available_languages[] = 'en_US';
			}
			self::$valid_locales = $available_languages;
		}
		return in_array( $locale, self::$valid_locales, true );
	}

	/**
	 * Registers the Locale Scalar type to the Schema.
	 *
	 * @return void
	 */
	public static function register_scalar() {
		register_graphql_scalar(
			'Locale',
			[
				'description'  => \__( 'A locale code, such as `en_US` or `de_DE`. The `Locale` scalar type represents a locale and validates that the value is a locale available on the server.', 'wp-graphql' ),
				'serialize'    => [ self::class, 'serialize' ],
				'parseValue'   => [ self::class, 'parseValue' ],
				'parseLiteral' => [ self::class, 'parseLiteral' ],
			]
		);
	}
}
