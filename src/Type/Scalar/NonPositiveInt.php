<?php

namespace WPGraphQL\Type\Scalar;

use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\Node;
use GraphQL\Utils\Utils;

/**
 * Class NonPositiveInt
 *
 * @package WPGraphQL\Type\Scalar
 */
class NonPositiveInt {

	/**
	 * Ensures the value is a non-positive integer.
	 *
	 * @param mixed $value
	 * @return int
	 * @throws InvariantViolation|Error
	 */
	private static function coerce( $value ) {
		if ( ! is_numeric( $value ) || $value > PHP_INT_MAX ) {
			// translators: %s is the value passed to the scalar
			throw new InvariantViolation( sprintf( __( 'NonPositiveInt cannot represent non-numeric value: %s', 'wp-graphql' ), Utils::printSafe( $value ) ) );
		}

		$intValue = (int) $value;

		if ( $intValue != $value ) { // phpcs:ignore
			// translators: %s is the value passed to the scalar
			throw new InvariantViolation( sprintf( __( 'NonPositiveInt cannot represent non-integer value: %s', 'wp-graphql' ), Utils::printSafe( $value ) ) );
		}

		if ( $intValue > 0 ) {
			// translators: %s is the value passed to the scalar
			throw new InvariantViolation( sprintf( __( 'NonPositiveInt cannot represent positive value: %s', 'wp-graphql' ), Utils::printSafe( $value ) ) );
		}
		return $intValue;
	}

	/**
	 * @param mixed $value
	 * @return int
	 * @throws InvariantViolation
	 */
	public static function serialize( $value ) {
		return self::coerce( $value );
	}

	/**
	 * @param mixed $value
	 * @return int
	 * @throws Error
	 */
	public static function parseValue( $value ) {
		try {
			return self::coerce( $value );
		} catch ( InvariantViolation $e ) {
			throw new Error( $e->getMessage() );
		}
	}

	/**
	 * @param Node                 $valueNode
	 * @param array<string,mixed>|null $variables
	 * @return int
	 * @throws Error
	 */
	public static function parseLiteral( Node $valueNode, ?array $variables = null ) {
		if ( ! $valueNode instanceof IntValueNode ) {
			// translators: %s is the kind of the value node
			throw new Error( sprintf( __( 'Query error: Can only parse integers got: %s', 'wp-graphql' ), $valueNode->kind ), [ $valueNode ] );
		}

		try {
			return self::coerce( $valueNode->value );
		} catch ( InvariantViolation $e ) {
			throw new Error( $e->getMessage() );
		}
	}

	/**
	 * @return void
	 */
	public static function register_scalar() {
		register_graphql_scalar(
			'NonPositiveInt',
			[
				'description'    => __( 'Integers that will have a value of 0 or less.', 'wp-graphql' ),
				'serialize'      => [ self::class, 'serialize' ],
				'parseValue'     => [ self::class, 'parseValue' ],
				'parseLiteral'   => [ self::class, 'parseLiteral' ],
				'specifiedByURL' => 'https://www.w3.org/TR/xmlschema-2/#nonPositiveInteger',
			]
		);
	}
}