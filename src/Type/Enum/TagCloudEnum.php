<?php
namespace WPGraphQL\Type;

register_graphql_enum_type( 'TagCloudEnum', [
	'description' => __( 'Taxonomy of widget resource type', 'wp-graphql' ),
	'values' => [
		'POST_TAG' => [
			'value' => 'post_tag',
		],
		'CATEGORY' => [
			'value' => 'category'
		], 
		'LINK_CATEGORY' => [
			'value'	=> 'link_category'
		],
	],
] );