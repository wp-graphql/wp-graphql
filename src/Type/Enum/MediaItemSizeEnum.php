<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Type\WPEnumType;

class MediaItemSizeEnum {

	/**
	 * Register the MediaItemSizeEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		/**
		 * This returns an empty array on the VIP Go platform.
		 */
		$sizes = get_intermediate_image_sizes(); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_intermediate_image_sizes_get_intermediate_image_sizes

		$values      = [];
		$image_sizes = ! empty( $sizes ) && is_array( $sizes ) ? $sizes : [
			'thumbnail',
			'medium',
			'medium_large',
			'large',
			'full',
		];

		/**
		 * Loop through the image_sizes
		 */
		foreach ( $image_sizes as $image_size ) {
			$values[ WPEnumType::get_safe_name( $image_size ) ] = [
				'description' => sprintf(
					// translators: %1$s is the image size.
					__( 'MediaItem with the %1$s size', 'wp-graphql' ),
					$image_size
				),
				'value'       => $image_size,
			];
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
