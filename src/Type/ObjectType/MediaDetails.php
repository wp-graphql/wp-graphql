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
				'description' => static function () {
					return __( 'File details for a Media Item', 'wp-graphql' );
				},
				'fields'      => static function () {
					return [
						'width'    => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'The width of the mediaItem', 'wp-graphql' );
							},
						],
						'height'   => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'The height of the mediaItem', 'wp-graphql' );
							},
						],
						'file'     => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The filename of the mediaItem', 'wp-graphql' );
							},
							'resolve'     => static function ( $media_details ) {
								return ! empty( $media_details['file'] ) ? basename( $media_details['file'] ) : null;
							},
						],
						'filePath' => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The path to the mediaItem relative to the uploads directory', 'wp-graphql' );
							},
							'resolve'     => static function ( $media_details ) {
								// Get the upload directory info
								$upload_dir           = wp_upload_dir();
								$relative_upload_path = wp_make_link_relative( $upload_dir['baseurl'] );

								if ( ! empty( $media_details['file'] ) ) {
									return path_join( $relative_upload_path, $media_details['file'] );
								}

								return null;
							},
						],
						'sizes'    => [
							'type'        => [
								'list_of' => 'MediaSize',
							],
							'args'        => [
								'exclude' => [ // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
									'type'        => [ 'list_of' => 'MediaItemSizeEnum' ],
									'description' => static function () {
										return __( 'The sizes to exclude. Will take precedence over `include`.', 'wp-graphql' );
									},
								],
								'include' => [
									'type'        => [ 'list_of' => 'MediaItemSizeEnum' ],
									'description' => static function () {
										return __( 'The sizes to include. Can be overridden by `exclude`.', 'wp-graphql' );
									},
								],
							],
							'description' => static function () {
								return __( 'The available sizes of the mediaItem', 'wp-graphql' );
							},
							'resolve'     => static function ( $media_details, array $args ) {
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
						'meta'     => [
							'type'        => 'MediaItemMeta',
							'description' => static function () {
								return __( 'Meta information associated with the mediaItem', 'wp-graphql' );
							},
							'resolve'     => static function ( $media_details ) {
								return ! empty( $media_details['image_meta'] ) ? $media_details['image_meta'] : null;
							},
						],
					];
				},
			]
		);
	}
}
