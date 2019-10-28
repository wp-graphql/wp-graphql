<?php
namespace WPGraphQL\Type\Union;

use WPGraphQL\Registry\TypeRegistry;

class TermObjectUnion {

	/**
	 * Registers the Type
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @access public
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_union_type(
			'TermObjectUnion',
			[
				'kind'        => 'union',
				'typeNames'   => self::get_possible_types(),
				'resolveType' => function( $value ) use ( $type_registry ) {

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
	 * @access public
	 * @return array
	 */
	public static function get_possible_types() {
		$possible_types = [];

		$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies();
		if ( ! empty( $allowed_taxonomies ) && is_array( $allowed_taxonomies ) ) {
			foreach ( $allowed_taxonomies as $allowed_taxonomy ) {
				if ( empty( $possible_types[ $allowed_taxonomy ] ) ) {
					$tax_object = get_taxonomy( $allowed_taxonomy );
					if ( isset( $tax_object->graphql_single_name ) ) {
						$possible_types[ $allowed_taxonomy ] = $tax_object->graphql_single_name;
					}
				}
			}
		}
		return $possible_types;
	}
}
add_action(
	'graphql_register_types',
	function( TypeRegistry $type_registry ) {

		$possible_types = [];

		$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies();
		if ( ! empty( $allowed_taxonomies ) && is_array( $allowed_taxonomies ) ) {
			foreach ( $allowed_taxonomies as $allowed_taxonomy ) {
				if ( empty( $possible_types[ $allowed_taxonomy ] ) ) {
					$tax_object = get_taxonomy( $allowed_taxonomy );
					if ( isset( $tax_object->graphql_single_name ) ) {
						$possible_types[ $allowed_taxonomy ] = $tax_object->graphql_single_name;
					}
				}
			}
		}

		$type_registry->register_union_type(
			'TermObjectUnion',
			[
				'kind'        => 'union',
				'typeNames'   => $possible_types,
				'resolveType' => function( $value ) use ( $type_registry ) {

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
);
