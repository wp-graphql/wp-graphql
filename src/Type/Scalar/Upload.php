<?php

namespace WPGraphQL\Type\Scalar;

use GraphQL\Error\UserError;
use GraphQL\Error\InvariantViolation;
use GraphQL\Utils\Utils;
use UnexpectedValueException;

/**
 * Class Upload
 *
 * Registers Upload scalar type to WPGraphQL schema.
 */
class Upload {

	/**
	 * Keys found for a file in the $_FILES array to validate against.
	 *
	 * @var string[]
	 */
	public static $validationFileKeys = [ 'name', 'type', 'size' ];

	/**
	 * Register the scalar Upload type.
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_scalar(
			'Upload',
			[
				'description'  => __( 'The `Upload` special type represents a file to be uploaded in the same HTTP request as specified by [graphql-multipart-request-spec](https: //github.com/jaydenseric/graphql-multipart-request-spec).', 'wp-graphql' ),
				'serialize'    => fn() => static::serialize(),
				'parseValue'   => fn( $value ) => static::parseValue( $value ),
				'parseLiteral' => fn( $value ) => static::parseLiteral( $value ),
			]
		);
	}

	/**
	 * Serializes an internal value to include in a response.
	 * 
	 * @throws InvariantViolation
	 *
	 * @return void
	 */
	public static function serialize() {
		throw new InvariantViolation( '`Upload` cannot be serialized' );
	}

	/**
	 * Parses an externally provided value (query variable) to use as an input.
	 * 
	 * @throws UnexpectedValueException
	 *
	 * @param mixed $value Value to be parsed.
	 * @return mixed
	 */
	public static function parseValue( $value ) {
		if ( false === static::arrayKeysExist( (array) $value ) ) {
			throw new UnexpectedValueException(
				sprintf(
					__( 'Could not get uploaded file, be sure to conform to GraphQL multipart request specification. Instead got: %s', 'wp-graphql' ),
					Utils::printSafe( $value )
				)
			);
		}

		// If not supplied, use the server's temp directory.
		if ( empty( $value['tmp_name'] ) ) {
			$tmp_dir           = get_temp_dir();
			$value['tmp_name'] = $tmp_dir . wp_unique_filename( $tmp_dir, $value['name'] );
		}

		return $value;
	}

	/**
	 * Parses an externally provided literal value (hardcoded in GraphQL query) to use as an input.
	 *
	 * @example
	 * {
	 *   upload(file: ".......")
	 * }
	 * 
	 * @throws UserError
	 *
	 * @param GraphQLLanguageASTNode $valueNode
	 */
	public static function parseLiteral( $value ) {
		throw new UserError(
			sprintf(
				__( '`Upload` cannot be hardcoded in query, be sure to conform to GraphQL multipart request specification. Instead got:  %s', 'wp-graphql' ),
				$value->kind
			),
			$value
		);
	}

	/**
	 * Check if an array of keys exist in the given array.
	 *
	 * @param array $value Value.
	 * @return bool
	 */
	private static function arrayKeysExist( array $value ) {
		foreach ( static::$validationFileKeys as $key ) {
			if ( ! array_key_exists( $key, $value ) ) {
				return false;
			}
		}

		return true;
	}
}
