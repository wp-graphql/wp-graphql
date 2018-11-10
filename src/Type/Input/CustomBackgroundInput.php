<?php
namespace WPGraphQL\Type;

register_graphql_input_type( 'CustomBackgroundInput', [
	'description' => __( 'Custom background values', 'wp-graphql' ),
	'fields' => [
		'imageId' => [
			'type'        => 'ID',
			'description' => __( 'The theme mod "background"\'s image attachment id', 'wp-graphql' ),
		],
		'preset' => [
			'type'        => 'String',
			'description' => __( 'The theme mod "background"\'s preset property', 'wp-graphql' ),
		],
		'size' => [
			'type'        => 'String',
			'description' => __( 'The theme mod "background"\'s css background-size property', 'wp-graphql' ),
		],
		'repeat' => [
			'type'        => 'String',
			'description' => __( 'The theme mod "background"\'s css background-repeat property', 'wp-graphql' ),
		],
		'attachment' => [
			'type'        => 'String',
			'description' => __( 'The theme mod "background"\'s css background-attachement property', 'wp-graphql' ),
		],
	],
] );