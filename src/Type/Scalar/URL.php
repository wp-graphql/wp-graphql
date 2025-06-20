<?php

namespace WPGraphQL\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Utils\Utils;

/**
 * Class URL
 *
 * The `URL` scalar type represents a valid URL.
 *
 * @package WPGraphQL\Type\Scalar
 */
class URL {

	/**
	 * Serializes an internal value to include in a response.
	 *
	 * @param mixed $value
	 * @return string|null
	 * @throws \GraphQL\Error\Error
	 */
	public static function serialize( $value ) {
		if ( ! is_string( $value ) || empty( $value ) ) {
			return null;
		}

		if ( false === filter_var( $value, FILTER_VALIDATE_URL ) ) {
			throw new Error(
				\esc_html(
					\sprintf(
						/* translators: %s: The invalid URL value */
						\__( 'Value is not a valid URL: %s', 'wp-graphql' ),
						Utils::printSafe( $value )
					)
				)
			);
		}

		$sanitized_url = \esc_url_raw( $value );

		if ( empty( $sanitized_url ) ) {
			throw new Error(
				\esc_html(
					\sprintf(
						/* translators: %s: The invalid URL value */
						\__( 'Value is not a valid URL: %s', 'wp-graphql' ),
						Utils::printSafe( $value )
					)
				)
			);
		}

		return $sanitized_url;
	}

	/**
	 * Parses an externally provided value (query variable) to use as an input
	 *
	 * @param mixed $value
	 * @return string
	 * @throws \GraphQL\Error\Error
	 *
	 * NOTE: `parseValue` is a required method for all Custom Scalars in `graphql-php`.
	 */
	public static function parseValue( $value ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		if ( ! is_string( $value ) || false === filter_var( $value, FILTER_VALIDATE_URL ) ) {
			throw new Error(
				\esc_html(
					\sprintf(
						/* translators: %s: The invalid URL value */
						\__( 'Value is not a valid URL: %s', 'wp-graphql' ),
						Utils::printSafe( $value )
					)
				)
			);
		}

		$sanitized_url = \esc_url_raw( $value );

		if ( empty( $sanitized_url ) ) {
			throw new Error(
				\esc_html(
					\sprintf(
						/* translators: %s: The invalid URL value */
						\__( 'Value is not a valid URL: %s', 'wp-graphql' ),
						Utils::printSafe( $value )
					)
				)
			);
		}

		return $sanitized_url;
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
	 * Registers the URL Scalar type to the Schema
	 *
	 * @return void
	 */
	public static function register_scalar() {
		register_graphql_scalar(
			'URL',
			[
				'description'  => \__( 'A Uniform Resource Locator (URL), which must be an absolute URL with a scheme, such as `https://www.wpgraphql.com`.', 'wp-graphql' ),
				'serialize'    => [ self::class, 'serialize' ],
				'parseValue'   => [ self::class, 'parseValue' ],
				'parseLiteral' => [ self::class, 'parseLiteral' ],
			]
		);
	}
}
