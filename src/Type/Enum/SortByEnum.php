<?php
namespace WPGraphQL\Type;

register_graphql_enum_type( 'SortByEnum', [
	'description' => __( 'Sorting order of widget resource type', 'wp-graphql' ),
	'values' => [
		'MENU_ORDER' => [
			'value'	=> 'menu_order'
		],
		'POST_TITLE' => [
			'value'	=> 'post_title'
		],
		'ID' => [
			'value'	=> 'id'
		]
	],
] );