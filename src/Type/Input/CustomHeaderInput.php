<?php
namespace WPGraphQL\Type;

register_graphql_input_type( 'CustomHeaderInput', [
	'description' => __( 'Custom header values' ),
	'fields' => [
		'imageId' => [
			'type'        => 'ID',
			'description' => __( 'The theme mod "header-image"\'s image attachment id', 'wp-graphql' ),
		],
		'thumbnailUrl' => [
			'type'        => 'String',
			'description' => __( 'The theme mod "header-image"\'s thumbnail url', 'wp-graphql' ),
		],
		'height' => [
			'type'        => 'Int',
			'description' => __( 'The theme mod "header-image"\'s display width in pixels', 'wp-graphql' ),
		],
		'width' => [
			'type'        => 'Int',
			'description' => __( 'The theme mod "header-image"\'s display height in pixels', 'wp-graphql' ),
		],
	]
] );