<?php

namespace WPGraphQL\Type;

register_graphql_input_type(
	'PostObjectsConnectionOrderbyInput',
	[
		'description' => __( 'Options for ordering the connection', 'wp-graphql' ),
		'fields'      => [
			'field' => [
				'type' => [
					'non_null' => 'PostObjectsConnectionOrderbyEnum',
				],
			],
			'order' => [
				'type' => 'OrderEnum',
			],
		],
	]
);
