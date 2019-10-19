<?php

namespace WPGraphQL\Type;

register_graphql_input_type( 'TermObjectsConnectionOrderbyInput', [
	'description' => __( 'Options for ordering the connection', 'wp-graphql' ),
	'fields'      => [
		'field' => [
			'type' => [
				'non_null' => 'TermObjectsConnectionOrderbyEnum',
			],
		],
		'order' => [
			'type' => 'OrderEnum',
		],
	],
] );
