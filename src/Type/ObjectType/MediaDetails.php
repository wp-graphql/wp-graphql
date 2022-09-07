<?php

namespace WPGraphQL\Type\ObjectType;

class MediaDetails {

	/**
	 * Register the MediaDetails type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
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
						'description' => __( 'The filename of the mediaItem', 'wp-graphql' ),
					],
					'sizes'  => [
						'type'        => [
							'list_of' => 'MediaSize',
						],
						'args'        => [
							'exclude' => [ // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
								'type'        => [ 'list_of' => 'MediaItemSizeEnum' ],
								'description' => __( 'The sizes to exclude. Will take precedence over `include`.', 'wp-graphql' ),
							],
							'include' => [
								'type'        => [ 'list_of' => 'MediaItemSizeEnum' ],
								'description' => __( 'The sizes to include. Can be overridden by `exclude`.', 'wp-graphql' ),
							],
						],
						'description' => __( 'The available sizes of the mediaItem', 'wp-graphql' ),
						'resolve'     => function ( $media_details, array $args ) {
							// Bail early.
							if ( empty( $media_details['sizes'] ) ) {
								return null;
							}

							// If the include arg is set, only include the sizes specified.
							if ( ! empty( $args['include'] ) ) {
								$media_details['sizes'] = array_intersect_key( $media_details['sizes'], array_flip( $args['include'] ) );
							}

							// If the exclude arg is set, exclude the sizes specified.
							if ( ! empty( $args['exclude'] ) ) {
								$media_details['sizes'] = array_diff_key( $media_details['sizes'], array_flip( $args['exclude'] ) );
							}

							$sizes = [];

							foreach ( $media_details['sizes'] as $size_name => $size ) {
								$size['ID']   = $media_details['ID'];
								$size['name'] = $size_name;
								$sizes[]      = $size;
							}

							return ! empty( $sizes ) ? $sizes : null;
						},
					],
					'meta'   => [
						'type'        => 'MediaItemMeta',
						'description' => __( 'Meta information associated with the mediaItem', 'wp-graphql' ),
						'resolve'     => function ( $media_details ) {
							return ! empty( $media_details['image_meta'] ) ? $media_details['image_meta'] : null;
						},
					],
				],
			]
		);
	}
}
