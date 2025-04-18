<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Type\WPEnumType;

class MimeTypeEnum {

	/**
	 * Register the MimeTypeEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		$values = [
			'IMAGE_JPEG' => [
				'value'       => 'image/jpeg',
				'description' => static function () {
					return __( 'An image in the JPEG format', 'wp-graphql' );
				},
			],
		];

		$allowed_mime_types = get_allowed_mime_types();

		if ( ! empty( $allowed_mime_types ) ) {
			$values = [];
			foreach ( $allowed_mime_types as $mime_type ) {
				$values[ WPEnumType::get_safe_name( $mime_type ) ] = [
					'value'       => $mime_type,
					'description' => static function () use ( $mime_type ) {
						return sprintf(
							// translators: %s is the mime type.
							__( '%s mime type.', 'wp-graphql' ),
							$mime_type
						);
					},
				];
			}
		}

		register_graphql_enum_type(
			'MimeTypeEnum',
			[
				'description' => static function () {
					return __( 'Media file type classification based on MIME standards. Used to identify and filter media items by their format and content type.', 'wp-graphql' );
				},
				'values'      => $values,
			]
		);
	}
}
