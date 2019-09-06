<?php

namespace WPGraphQL\Type;

register_graphql_input_type( 'UsersConnectionOrderbyInput', [
	'description' => __( 'Options for ordering the connection', 'wp-graphql' ),
	'fields'      => [
		'field' => [
			'type' => [
				'non_null' => 'UsersConnectionOrderbyEnum',
			],
		],
		'order' => [
			'type' => 'OrderEnum',
		],
	],
] );