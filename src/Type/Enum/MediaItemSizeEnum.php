<?php

namespace WPGraphQL\Type\Enum;

	use WPGraphQL\Type\WPEnumType;

class MediaItemSizeEnum {

	/**
	 * Get information about available image sizes
	 *
	 * @param string $size Optional. The size to get information for.
	 * @return array<string, array{width: int, height: int, crop: bool}>|null
	 *
	 * @since 2.3.0
	 */
	protected static function get_image_sizes( $size = '' ): ?array {

		$wp_additional_image_sizes = \wp_get_additional_image_sizes();

		$sizes                        = [];
		$get_intermediate_image_sizes = \get_intermediate_image_sizes(); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_intermediate_image_sizes_get_intermediate_image_sizes

		// Create the full array with sizes and crop info
		foreach ( $get_intermediate_image_sizes as $_size ) {
			if ( in_array( $_size, [ 'thumbnail', 'medium', 'medium_large', 'large', 'full' ], true ) ) {
				$sizes[ $_size ]['width']  = \get_option( $_size . '_size_w' );
				$sizes[ $_size ]['height'] = \get_option( $_size . '_size_h' );
				$sizes[ $_size ]['crop']   = (bool) \get_option( $_size . '_crop' );
			} elseif ( isset( $wp_additional_image_sizes[ $_size ] ) ) {
				$sizes[ $_size ] = [
					'width'  => $wp_additional_image_sizes[ $_size ]['width'],
					'height' => $wp_additional_image_sizes[ $_size ]['height'],
					'crop'   => $wp_additional_image_sizes[ $_size ]['crop'],
				];
			}
		}

		// Get only 1 size if found
		if ( $size ) {
			if ( isset( $sizes[ $size ] ) ) {
				return $sizes[ $size ];
			} else {
				return null;
			}
		}
		return $sizes;
	}

	/**
	 * Register the MediaItemSizeEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		/**
		 * This returns an empty array on the VIP Go platform.
		 */
		$sizes = self::get_image_sizes();

		$values      = [];
		$image_sizes = ! empty( $sizes ) ? $sizes : [];

		/**
		 * Loop through the image_sizes
		 */
		foreach ( $image_sizes as $image_size => $image_size_dimensions ) {
			$values[ WPEnumType::get_safe_name( $image_size ) ] = [
				'description' => static function () use ( $image_size, $image_size_dimensions ) {
					return self::get_value_description( $image_size, $image_size_dimensions );
				},
				'value'       => $image_size,
				'dimensions'  => $image_size_dimensions,
			];
		}

		register_graphql_enum_type(
			'MediaItemSizeEnum',
			[
				'description' => static function () {
					return __( 'Predefined image size variations. Represents the standard image dimensions available for media assets.', 'wp-graphql' );
				},
				'values'      => $values,
			]
		);
	}

	/**
	 * Get the description for a media item size
	 *
	 * @param string                 $image_size The size of the image.
	 * @param array<string,bool|int> $image_size_dimensions The dimensions of the image.
	 * @return string The description for the media item size.
	 *
	 * @since 2.3.0
	 */
	protected static function get_value_description( string $image_size, array $image_size_dimensions ): string {
		switch ( $image_size ) {
			case 'thumbnail':
				$description = sprintf(
					// translators: %1$s is the width of the image, %2$s is the height of the image
					__( 'Small image preview suitable for thumbnails and listings. (%1$sx%2$s)', 'wp-graphql' ),
					$image_size_dimensions['width'],
					$image_size_dimensions['height']
				);
				break;
			case 'medium':
				$description = sprintf(
					// translators: %1$s is the width of the image, %2$s is the height of the image
					__( 'Medium image preview typically suitable for listings and detail views. (%1$sx%2$s)', 'wp-graphql' ),
					$image_size_dimensions['width'],
					$image_size_dimensions['height']
				);
				break;
			case 'medium_large':
				$description = sprintf(
					// translators: %1$s is the width of the image, %2$s is the height of the image
					__( 'Medium-to-large image preview suitable for listings and detail views. (%1$sx%2$s)', 'wp-graphql' ),
					$image_size_dimensions['width'],
					$image_size_dimensions['height']
				);
				break;
			case 'large':
				$description = sprintf(
					// translators: %1$s is the width of the image, %2$s is the height of the image
					__( 'Large image preview suitable for detail views. (%1$sx%2$s)', 'wp-graphql' ),
					$image_size_dimensions['width'],
					$image_size_dimensions['height']
				);
				break;
			case 'full':
				$description = __( 'Full-size image.', 'wp-graphql' );
				break;
			default:
				$description = sprintf(
					// translators: %1$s is the width of the image, %2$s is the height of the image
					__( 'Custom Image Size. (%1$sx%2$s)', 'wp-graphql' ),
					$image_size_dimensions['width'],
					$image_size_dimensions['height']
				);
		}

		return $description;
	}
}
