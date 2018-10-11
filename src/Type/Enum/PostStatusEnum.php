<?php

namespace WPGraphQL\Type;

$post_status_enum_values = [
	'name'  => 'PUBLISH',
	'value' => 'publish',
];

$post_stati = get_post_stati();

if ( ! empty( $post_stati ) && is_array( $post_stati ) ) {
	/**
	 * Reset the array
	 */
	$post_status_enum_values = [];
	/**
	 * Loop through the post_stati
	 */
	foreach ( $post_stati as $status ) {
		$post_status_enum_values[ WPEnumType::get_safe_name( $status ) ] = [
			'description' => sprintf( __( 'Objects with the %1$s status', 'wp-graphql' ), $status ),
			'value'       => $status,
		];
	}
}

register_graphql_enum_type( 'PostStatusEnum', [
	'description' => __( 'The status of the object.', 'wp-graphql' ),
	'values'      => $post_status_enum_values,
] );
