<?php

namespace WPGraphQL\Type\ObjectType;

class MediaSize {

	/**
	 * Register the MediaSize
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_object_type(
			'MediaSize',
			[
				'description' => static function () {
					return __( 'Details of an available size for a media item', 'wp-graphql' );
				},
				'fields'      => static function () {
					return [
						'name'      => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The referenced size name', 'wp-graphql' );
							},
						],
						'file'      => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The filename of the referenced size', 'wp-graphql' );
							},
						],
						'filePath'  => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The path of the file for the referenced size (default size is full)', 'wp-graphql' );
							},
							'resolve'     => static function ( $image ) {
								if ( ! empty( $image['ID'] ) ) {
									$original_file  = get_attached_file( absint( $image['ID'] ) );
									$attachment_url = wp_get_attachment_url( $image['ID'] );

									if ( ! empty( $original_file ) && ! empty( $image['file'] ) && ! empty( $attachment_url ) ) {
										// Return the relative path for the specific size
										return path_join( dirname( wp_make_link_relative( $attachment_url ) ), $image['file'] );
									}
								} elseif ( ! empty( $image['file'] ) ) {
									return wp_make_link_relative( $image['file'] );
								}

								return null;
							},
						],
						'width'     => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The width of the referenced size', 'wp-graphql' );
							},
						],
						'height'    => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The height of the referenced size', 'wp-graphql' );
							},
						],
						'mimeType'  => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The mime type of the referenced size', 'wp-graphql' );
							},
							'resolve'     => static function ( $image ) {
								return ! empty( $image['mime-type'] ) ? $image['mime-type'] : null;
							},
						],
						'fileSize'  => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'The filesize of the resource', 'wp-graphql' );
							},
							'resolve'     => static function ( $image ) {
								if ( ! empty( $image['ID'] ) && ! empty( $image['file'] ) ) {
									$original_file = get_attached_file( absint( $image['ID'] ) );
									$filesize_path = ! empty( $original_file ) ? path_join( dirname( $original_file ), $image['file'] ) : null;

									return ! empty( $filesize_path ) ? filesize( $filesize_path ) : null;
								}

								return null;
							},
						],
						'sourceUrl' => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The url of the referenced size', 'wp-graphql' );
							},
							'resolve'     => static function ( $image ) {
								$src_url = null;

								if ( ! empty( $image['ID'] ) ) {
									$src = wp_get_attachment_image_src( absint( $image['ID'] ), $image['name'] );
									if ( ! empty( $src ) ) {
										$src_url = $src[0];
									}
								} elseif ( ! empty( $image['file'] ) ) {
									$src_url = $image['file'];
								}

								return $src_url;
							},
						],
					];
				},
			]
		);
	}
}
