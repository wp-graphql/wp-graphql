<?php

namespace WPGraphQL\Type;

$allowed_taxonomies = \WPGraphQL::$allowed_taxonomies;

$values = [];

/**
 * Loop through the taxonomies and create an array
 * of values for use in the enum type.
 */
foreach ( $allowed_taxonomies as $taxonomy ) {
	$values[ WPEnumType::get_safe_name( get_taxonomy( $taxonomy )->graphql_single_name ) ] = [
		'value' => $taxonomy,
	];
}

register_graphql_enum_type( 'TaxonomyEnum', [
	'description' => __( 'Allowed taxonomies', 'wp-graphql' ),
	'values'      => $values
] );
