<?php

namespace WPGraphQL\Type;

register_graphql_enum_type(
	'PostObjectsConnectionDateColumnEnum',
	[
		'description' => __( 'The column to use when filtering by date', 'wp-graphql' ),
		'values'      => [
			'DATE'     => [
				'value' => 'post_date',
			],
			'MODIFIED' => [
				'value' => 'post_modified',
			],
		],
	]
);
