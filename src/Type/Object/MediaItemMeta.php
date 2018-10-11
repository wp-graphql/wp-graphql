<?php

namespace WPGraphQL\Type;

register_graphql_object_type( 'MediaItemMeta', [
	'description' => __( 'Meta connected to a MediaItem', 'wp-graphql' ),
	'fields'      => [
		'aperture'         => [
			'type' => 'Float',
		],
		'credit'           => [
			'type' => 'String',
		],
		'camera'           => [
			'type' => 'String',
		],
		'caption'          => [
			'type' => 'String',
		],
		'createdTimestamp' => [
			'type'    => 'Int',
			'resolve' => function( $meta, $args, $context, $info ) {
				return ! empty( $meta['created_timestamp'] ) ? $meta['created_timestamp'] : null;
			},
		],
		'copyright'        => [
			'type' => 'String',
		],
		'focalLength'      => [
			'type'    => 'Int',
			'resolve' => function( $meta, $args, $context, $info ) {
				return ! empty( $meta['focal_length'] ) ? $meta['focal_length'] : null;
			},
		],
		'iso'              => [
			'type' => 'Int',
		],
		'shutterSpeed'     => [
			'type'    => 'Float',
			'resolve' => function( $meta, $args, $context, $info ) {
				return ! empty( $meta['shutter_speed'] ) ? $meta['shutter_speed'] : null;
			},
		],
		'title'            => [
			'type' => 'String',
		],
		'orientation'      => [
			'type' => 'String',
		],
		'keywords'         => [
			'type' => [
				'list_of' => 'String',
			],
		],
	],
] );
