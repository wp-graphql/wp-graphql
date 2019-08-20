<?php
namespace WPGraphQL\Type;

use WPGraphQL\Registry\TypeRegistry;

add_action( 'init_type_registry', function( TypeRegistry $type_registry ) {

	$possible_types = [];

	$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies();
	if ( ! empty( $allowed_taxonomies ) && is_array( $allowed_taxonomies ) ) {
		foreach ( $allowed_taxonomies as $allowed_taxonomy ) {
			if ( empty( $possible_types[ $allowed_taxonomy ] ) ) {
				$tax_object = get_taxonomy( $allowed_taxonomy );
				if ( isset( $tax_object->graphql_single_name ) ) {
					$possible_types[ $allowed_taxonomy ] = $type_registry->get_type( $tax_object->graphql_single_name );
				}
			}
		}
	}

	register_graphql_union_type(
		'TermObjectUnion',
		[
			'types'       => $possible_types,
			'resolveType' => function( $value ) use ( $type_registry ) {

				$type = null;
				if ( isset( $value->taxonomyName) ) {
					$tax_object = get_taxonomy( $value->taxonomyName );
					if ( isset( $tax_object->graphql_single_name ) ) {
						$type = $type_registry->get_type( $tax_object->graphql_single_name );
					}
				}

				return ! empty( $type ) ? $type : null;

			},
		]
	);

});
