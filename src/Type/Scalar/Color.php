<?php

namespace WPGraphQL\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Utils\Utils;

/**
 * Class Color
 *
 * The `Color` scalar type represents a color value.
 * It can accept values in HEX, RGB, or RGBA format.
 *
 * @package WPGraphQL\Type\Scalar
 */
class Color {

	/**
	 * Normalizes a color string to RGBA format.
	 *
	 * @param string $color The color string to normalize.
	 * @return string The normalized RGBA color string.
	 */
	private static function normalize_color( string $color ): string {
		// Trim whitespace
		$color = trim( $color );

		// If HEX, convert to RGBA
		if ( preg_match( '/^#([a-f0-9]{3}){1,2}([a-f0-9]{2})?$/i', $color ) ) {
			$hex = substr( $color, 1 );

			if ( 3 === strlen( $hex ) ) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}

			$r = hexdec( substr( $hex, 0, 2 ) );
			$g = hexdec( substr( $hex, 2, 2 ) );
			$b = hexdec( substr( $hex, 4, 2 ) );
			$a = 1;

			if ( 8 === strlen( $hex ) ) {
				$a = round( hexdec( substr( $hex, 6, 2 ) ) / 255, 2 );
			}

			return "rgba($r,$g,$b,$a)";
		}

		// if RGB, convert to RGBA
		if ( preg_match( '/^rgb\((\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*)\)$/i', $color, $matches ) ) {
			return 'rgba(' . str_replace( ' ', '', $matches[1] ) . ',1)';
		}

		// Return RGBA as-is, but with spaces removed.
		if ( preg_match( '/^rgba\((\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*[\d\.]+\s*)\)$/i', $color, $matches ) ) {
			return 'rgba(' . str_replace( ' ', '', $matches[1] ) . ')';
		}

		return $color;
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

		if ( ! is_string( $value ) ) {
			throw new \GraphQL\Error\InvariantViolation( \esc_html__( 'Color value must be a string.', 'wp-graphql' ) );
		}

		if ( ! self::is_valid_color( $value ) ) {
			throw new \GraphQL\Error\InvariantViolation(
				\esc_html(
					\sprintf(
						/* translators: %s: The invalid Color value */
						\__( 'Value is not a valid Color: %s', 'wp-graphql' ),
						Utils::printSafe( $value )
					)
				)
			);
		}

		return self::normalize_color( $value );
	}

	/**
	 * Parses an externally provided value (query variable) to use as an input.
	 *
	 * @param mixed $value
	 * @return string
	 * @throws \GraphQL\Error\Error
	 */
	public static function parseValue( $value ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( ! is_string( $value ) || ! self::is_valid_color( $value ) ) {
			throw new Error(
				\esc_html(
					\sprintf(
						/* translators: %s: The invalid Color value */
						\__( 'Value is not a valid Color: %s', 'wp-graphql' ),
						Utils::printSafe( $value )
					)
				)
			);
		}
		return self::normalize_color( $value );
	}

	/**
	 * Parses an externally provided literal value (hardcoded in GraphQL query) to use as an input.
	 *
	 * @param \GraphQL\Language\AST\Node $valueNode
	 * @param array<string,mixed>|null   $variables
	 * @return string
	 * @throws \GraphQL\Error\Error
	 */
	public static function parseLiteral( Node $valueNode, ?array $variables = null ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( ! $valueNode instanceof StringValueNode ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Error( 'Query error: Can only parse strings got: ' . $valueNode->kind, [ $valueNode ] );
		}

		return self::parseValue( $valueNode->value );
	}

	/**
	 * Validates if the given string is a valid HEX, RGB, or RGBA color.
	 *
	 * @param string $color
	 */
	private static function is_valid_color( string $color ): bool {
		// HEX: for example #f00, #ff0000, #ff0000ff
		if ( preg_match( '/^#([a-f0-9]{3}){1,2}([a-f0-9]{2})?$/i', $color ) ) {
			return true;
		}

		// RGB: for exmaple rgb(255, 0, 0)
		if ( preg_match( '/^rgb\((\s*\d{1,3}\s*,){2}\s*\d{1,3}\s*\)$/', $color ) ) {
			return true;
		}

		// RGBA: for example rgba(255, 0, 0, 0.5)
		if ( preg_match( '/^rgba\((\s*\d{1,3}\s*,){3}\s*[\d\.]+\s*\)$/', $color ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Registers the Color Scalar type to the Schema.
	 *
	 * @return void
	 */
	public static function register_scalar() {
		register_graphql_scalar(
			'Color',
			[
				'description'  => \__( 'A string representing a color, which can be in HEX, RGB, or RGBA format. For example: `#000000`, `rgb(255, 0, 0)`, or `rgba(255, 0, 0, 0.5)`. Default output if not otherwise specifed is RGBA.', 'wp-graphql' ),
				'serialize'    => [ self::class, 'serialize' ],
				'parseValue'   => [ self::class, 'parseValue' ],
				'parseLiteral' => [ self::class, 'parseLiteral' ],
			]
		);
	}
}
