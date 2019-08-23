<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Type\WPEnumType;

class MediaItemSizeEnum {
	public static function register_type() {
		$values = [];

		$sizes = get_intermediate_image_sizes();

		$image_sizes = ! empty( $sizes ) && is_array( $sizes ) ? $sizes : [
			'thumbnail',
			'medium',
			'medium_large',
			'large',
			'full',
		];

		if ( ! empty( $image_sizes ) && is_array( $image_sizes ) ) {
			/**
			 * Reset the array
			 */
			$values = [];
			/**
			 * Loop through the image_sizes
			 */
			foreach ( $image_sizes as $image_size ) {

				$values[ WPEnumType::get_safe_name( $image_size ) ] = [
					'description' => sprintf( __( 'MediaItem with the %1$s size', 'wp-graphql' ), $image_size ),
					'value'       => $image_size,
				];
			}
		}

		register_graphql_enum_type(
			'MediaItemSizeEnum',
			[
				'description' => __( 'The size of the media item object.', 'wp-graphql' ),
				'values'      => $values,
			]
		);

	}
}
