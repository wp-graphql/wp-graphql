<?php
namespace WPGraphQL\Type;

register_graphql_enum_type( 'ImageSizeEnum', [
	'description' => __( 'Size of image', 'wp-graphql' ),
	'values' => [
		'THUMBNAIL' => [
			'value' => 'thumbnail',
		], 
		'MEDIUM' => [
			'value' => 'medium',
		], 
		'LARGE' => [
			'value' => 'large',
		],
		'FULLSIZE' => [
			'value' => 'fullsize',
		],
	]
] );