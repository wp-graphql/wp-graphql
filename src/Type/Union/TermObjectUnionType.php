<?php
namespace WPGraphQL\Type\Union;

use WPGraphQL\Types;
use WPGraphQL\Type\WPUnionType;

/**
 * Class TermObjectUnionType
 *
 * In some situations, the type of term cannot be known until query time. The termObjectUnion allows for connections to
 * be queried and resolved to a number of types.
 *
 * @package WPGraphQL\Type\Union
 */
class TermObjectUnionType extends WPUnionType {

	/**
	 * This holds an array of the possible types that can be resolved by this union
	 * @var array
	 */
	private static $possible_types;

	/**
	 * TermObjectUnionType constructor.
	 */
	public function __construct() {

		self::getPossibleTypes();

		$config = [
			'name' => 'TermObjectUnion',
			'types' => self::$possible_types,
			'resolveType' => function( $value ) {
				return ! empty( $value->taxonomy ) ? Types::term_object( $value->taxonomy ) : null;
			},
		];

		parent::__construct( $config );

	}

	/**
	 * This defines the possible types that can be resolved by this union
	 *
	 * @return array|null An array of possible types that can be resolved by the union
	 */
	public function getPossibleTypes() {

		if ( null === self::$possible_types ) {
			self::$possible_types = [];
		}

		$allowed_taxonomies = \WPGraphQL::$allowed_taxonomies;
		if ( ! empty( $allowed_taxonomies ) && is_array( $allowed_taxonomies ) ) {
			foreach ( $allowed_taxonomies as $allowed_taxonomy ) {
				if ( empty( self::$possible_types[ $allowed_taxonomy ] ) ) {
					self::$possible_types[ $allowed_taxonomy ] = Types::term_object( $allowed_taxonomy );
				}
			}
		}

		return ! empty( self::$possible_types ) ? self::$possible_types : null;

	}

}
