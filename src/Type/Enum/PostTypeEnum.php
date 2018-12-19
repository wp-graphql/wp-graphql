<?php

namespace WPGraphQL\Type;

$values = [];

/**
 * Get the allowed taxonomies
 */
$allowed_post_types = \WPGraphQL::get_allowed_post_types();

/**
 * Loop through the taxonomies and create an array
 * of values for use in the enum type.
 */
if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {
	foreach ( $allowed_post_types as $post_type ) {

		$values[ WPEnumType::get_safe_name( $post_type ) ] = [
			'value' => $post_type,
		];
	}
}

register_graphql_enum_type( 'PostTypeEnum', [
	'description' => __( 'Allowed Post Types', 'wp-graphql' ),
	'values'      => $values
] );
