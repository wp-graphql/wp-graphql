<?php
namespace WPGraphQL\Type;

use WPGraphQL\Types;

$possible_types = [];

$allowed_taxonomies = \WPGraphQL::$allowed_taxonomies;
if ( ! empty( $allowed_taxonomies ) && is_array( $allowed_taxonomies ) ) {
	foreach ( $allowed_taxonomies as $allowed_taxonomy ) {
		if ( empty( $possible_types[ $allowed_taxonomy ] ) ) {
			$possible_types[ $allowed_taxonomy ] = Types::term_object( $allowed_taxonomy );
		}
	}
}

register_graphql_union_type( 'TermObjectUnion', [
	'types'       => $possible_types,
	'resolveType' => function ( $value ) {
		return ! empty( $value->taxonomy ) ? Types::term_object( $value->taxonomy ) : null;
	},
] );
