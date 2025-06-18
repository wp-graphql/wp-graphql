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
	 * Serializes an internal value to include in a response.
	 *
	 * @param mixed $value
	 * @return string|null
	 * @throws Error
	 */
	public static function serialize( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}

		if ( ! is_string( $value ) ) {
			throw new Error( \__( 'Color value must be a string.', 'wp-graphql' ) );
		}

		if ( ! self::is_valid_color( $value ) ) {
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

		return $value;
	}

	/**
	 * Parses an externally provided value (query variable) to use as an input.
	 *
	 * @param mixed $value
	 * @return string
	 * @throws Error
	 */
	public static function parseValue( $value ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return self::serialize( $value );
	}

	/**
	 * Parses an externally provided literal value (hardcoded in GraphQL query) to use as an input.
	 *
	 * @param Node                $valueNode
	 * @param array<string,mixed>|null $variables
	 * @return string
	 * @throws Error
	 */
	public static function parseLiteral( $valueNode, ?array $variables = null ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( ! $valueNode instanceof StringValueNode ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Error( 'Query error: Can only parse strings got: ' . $valueNode->kind, [ $valueNode ] );
		}

		return self::serialize( $valueNode->value );
	}

	/**
	 * Validates if the given string is a valid HEX, RGB, or RGBA color.
	 *
	 * @param string $color
	 * @return bool
	 */
	private static function is_valid_color( string $color ): bool {
		// HEX: #f00, #ff0000, #ff0000ff
		if ( preg_match( '/^#([a-f0-9]{3}){1,2}([a-f0-9]{2})?$/i', $color ) ) {
			return true;
		}

		// RGB: rgb(255, 0, 0)
		if ( preg_match( '/^rgb\((\s*\d{1,3}\s*,){2}\s*\d{1,3}\s*\)$/', $color ) ) {
			return true;
		}

		// RGBA: rgba(255, 0, 0, 0.5)
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
				'description'  => \__( 'A string representing a color, which can be in HEX, RGB, or RGBA format. For example: `#000000`, `rgb(255, 0, 0)`, or `rgba(255, 0, 0, 0.5)`.', 'wp-graphql' ),
				'serialize'    => [ self::class, 'serialize' ],
				'parseValue'   => [ self::class, 'parseValue' ],
				'parseLiteral' => [ self::class, 'parseLiteral' ],
			]
		);
	}
}