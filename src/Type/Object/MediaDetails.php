<?php

namespace WPGraphQL\Type;

register_graphql_object_type(
	'MediaDetails',
	[
		'description' => __( 'File details for a Media Item', 'wp-graphql' ),
		'fields'      => [
			'width'  => [
				'type'        => 'Int',
				'description' => __( 'The width of the mediaItem', 'wp-graphql' ),
			],
			'height' => [
				'type'        => 'Int',
				'description' => __( 'The height of the mediaItem', 'wp-graphql' ),
			],
			'file'   => [
				'type'        => 'String',
				'description' => __( 'The height of the mediaItem', 'wp-graphql' ),
			],
			'sizes'  => [
				'type'        => [
					'list_of' => 'MediaSize',
				],
				'description' => __( 'The available sizes of the mediaItem', 'wp-graphql' ),
				'resolve'     => function( $media_details, $args, $context, $info ) {
					if ( ! empty( $media_details['sizes'] ) ) {
						foreach ( $media_details['sizes'] as $size_name => $size ) {
							$size['ID']   = $media_details['ID'];
							$size['name'] = $size_name;
							$sizes[]      = $size;
						}
					}

					return ! empty( $sizes ) ? $sizes : null;
				},
			],
			'meta'   => [
				'type'    => 'MediaItemMeta',
				'resolve' => function( $media_details, $args, $context, $info ) {
					return ! empty( $media_details['image_meta'] ) ? $media_details['image_meta'] : null;
				},
			],
		],
	]
);
