<?php

namespace WPGraphQL\Type;

use WPGraphQL\Types;

$possible_types     = [];
$allowed_post_types = \WPGraphQL::$allowed_post_types;
if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {
	foreach ( $allowed_post_types as $allowed_post_type ) {
		if ( empty( $possible_types[ $allowed_post_type ] ) ) {
			$possible_types[ $allowed_post_type ] = Types::post_object( $allowed_post_type );
		}
	}
}

register_graphql_union_type( 'PostObjectUnion', [
	'name'        => 'PostObjectUnion',
	'types'       => $possible_types,
	'resolveType' => function( $value ) {
		return ! empty( $value->post_type ) ? Types::post_object( $value->post_type ) : null;
	},
] );
