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
	 * Validates that a string is well-formed HTML.
	 *
	 * @param string $html The string to check.
	 * @throws \GraphQL\Error\Error If the HTML is not well-formed.
	 */
	private static function validate_html( string $html ): void {
		if ( empty( trim( $html ) ) ) {
			return;
		}

		$internal_errors = libxml_use_internal_errors( true );
		$doc             = new \DOMDocument();
		// The LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD flags prevent DOMDocument from adding implied html/body tags.
		// mb_convert_encoding is used to prevent issues with character encoding.
		$encoded_html = mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' );

		if ( false === $encoded_html ) {
			throw new Error( esc_html__( 'Failed to encode HTML entities.', 'wp-graphql' ) );
		}
		$doc->loadHTML( $encoded_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		$errors = libxml_get_errors();
		libxml_clear_errors();
		libxml_use_internal_errors( $internal_errors );

		if ( ! empty( $errors ) ) {
			// We can inspect errors here if we want to be more lenient, but for now any error is a failure.
			throw new Error( esc_html__( 'Invalid HTML: The provided HTML is not well-formed.', 'wp-graphql' ) );
		}
	}

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
		$sanitized_html = wp_kses_post( $value );

		// Normalize tag and attribute names to lowercase for consistent output.
		return preg_replace_callback(
			'/(<\/?)([a-zA-Z0-9]+)([^>]*>)/',
			static function ( $matches ) {
				$tag_open              = $matches[1];
				$tag_name              = strtolower( $matches[2] );
				$attributes_part       = $matches[3];
				$normalized_attributes = preg_replace_callback(
					'/([a-zA-Z0-9\-]+)(?=\s*=)/',
					static function ( $attr_matches ) {
						return strtolower( $attr_matches[0] );
					},
					$attributes_part
				);
				return $tag_open . $tag_name . $normalized_attributes;
			},
			$sanitized_html
		) ?? $sanitized_html;
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
		self::validate_html( (string) $value );
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
			throw new Error(
				esc_html(
					sprintf(
						/* translators: %s: The GraphQL node kind. */
						__( 'Query error: Can only parse strings got: %s', 'wp-graphql' ),
						$valueNode->kind
					)
				),
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				[ $valueNode ]
			);
		}
		self::validate_html( $valueNode->value );
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
