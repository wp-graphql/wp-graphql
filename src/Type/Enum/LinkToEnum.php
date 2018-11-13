<?php
namespace WPGraphQL\Type;

register_graphql_enum_type( 'LinkToEnum', [
	'description' => __( 'Destination type of link', 'wp-graphql' ),
	'values' => [
		'NONE' => [
			'value' => null,
		],
		'POST' => [
			'value' => 'post',
		],
		'FILE' => [
			'value' => 'file'
		],
		'CUSTOM' => [
			'value' => 'custom'
		] 
	],
] );