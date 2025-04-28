<?php
namespace WPGraphQL\Type\Union;

use WPGraphQL\Registry\TypeRegistry;

/**
 * Class TermObjectUnion
 *
 * @package WPGraphQL\Type\Union
 * @deprecated use TermNode interface instead
 */
class TermObjectUnion {

	/**
	 * Registers the Type
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @throws \Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {
		register_graphql_union_type(
			'TermObjectUnion',
			[
				'kind'        => 'union',
				'typeNames'   => self::get_possible_types(),
				'description' => static function () {
					return __( 'Union between the Category, Tag and PostFormatPost types', 'wp-graphql' );
				},
				'resolveType' => static function ( $value ) use ( $type_registry ) {
					_doing_it_wrong( 'TermObjectUnion', esc_attr__( 'The TermObjectUnion GraphQL type is deprecated. Use the TermNode interface instead.', 'wp-graphql' ), '1.14.1' );

					$type = null;
					if ( isset( $value->taxonomyName ) ) {
						$tax_object = get_taxonomy( $value->taxonomyName );
						if ( isset( $tax_object->graphql_single_name ) ) {
							$type = $type_registry->get_type( $tax_object->graphql_single_name );
						}
					}

					return ! empty( $type ) ? $type : null;
				},
			]
		);
	}

	/**
	 * Returns a list of possible types for the union
	 *
	 * @return array<string,string>
	 */
	public static function get_possible_types() {
		$possible_types     = [];
		$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies( 'objects', [ 'graphql_kind' => 'object' ] );

		foreach ( $allowed_taxonomies as $tax_object ) {
			if ( empty( $possible_types[ $tax_object->name ] ) ) {
				if ( isset( $tax_object->graphql_single_name ) ) {
					$possible_types[ $tax_object->name ] = $tax_object->graphql_single_name;
				}
			}
		}

		return $possible_types;
	}
}
