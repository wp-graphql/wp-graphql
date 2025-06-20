<?php

namespace WPGraphQL\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;

/**
 * Class HTML
 *
 * @package WPGraphQL\Type\Scalar
 */
class HTML {

	/**
	 * A central private method to sanitize HTML strings.
	 *
	 * This method first strips all script tags and their content, then runs the
	 * string through `wp_kses_post` to ensure a safe, standard set of HTML is allowed.
	 *
	 * @param string $value The raw value to be sanitized.
	 * @return string The sanitized HTML string.
	 */
	private static function sanitize_html( string $value ): string {
		// Strip script tags and their content entirely.
		$value = preg_replace( '#<script(.*?)>(.*?)</script>#is', '', $value ) ?? '';
		// Use the standard WordPress function for sanitizing post content.
		return wp_kses_post( $value );
	}

	/**
	 * Serializes an internal value to include in a response.
	 *
	 * @param mixed $value
	 */
	public static function serialize( $value ): string {
		return self::sanitize_html( (string) $value );
	}

	/**
	 * Parses an externally provided value (query variable) to use as an input.
	 *
	 * @param mixed $value
	 */
	public static function parseValue( $value ): string { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return self::sanitize_html( (string) $value );
	}

	/**
	 * Parses an externally provided literal value (hardcoded in GraphQL query) to use as an input.
	 *
	 * @param \GraphQL\Language\AST\Node $valueNode
	 * @param array<string,mixed>|null   $variables
	 * @throws \GraphQL\Error\Error
	 */
	public static function parseLiteral( $valueNode, ?array $variables = null ): string { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( ! $valueNode instanceof StringValueNode ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new Error( 'Query error: Can only parse strings got: ' . $valueNode->kind, [ $valueNode ] );
		}
		return self::sanitize_html( $valueNode->value );
	}

	/**
	 * Registers the HTML Scalar type to the Schema
	 *
	 * @return void
	 */
	public static function register_scalar() {
		register_graphql_scalar(
			'HTML',
			[
				'description'  => __( 'A string containing HTML code, sanitized to remove potentially malicious code and allow a defined set of tags and attributes suitable for general-purpose content.', 'wp-graphql' ),
				'serialize'    => [ self::class, 'serialize' ],
				'parseValue'   => [ self::class, 'parseValue' ],
				'parseLiteral' => [ self::class, 'parseLiteral' ],
			]
		);
	}
}
