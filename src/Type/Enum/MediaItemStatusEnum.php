<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Type\WPEnumType;

class MediaItemStatusEnum {

	/**
	 * Register the MediaItemStatusEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		$values = [];

		$post_stati = [
			'inherit'    => static function () {
				return __( 'Media that inherits its publication status from the parent content', 'wp-graphql' );
			},
			'private'    => static function () {
				return __( 'Media visible only to users with appropriate permissions', 'wp-graphql' );
			},
			'trash'      => static function () {
				return __( 'Media marked for deletion but still recoverable', 'wp-graphql' );
			},
			'auto-draft' => static function () {
				return __( 'Automatically created media that has not been finalized', 'wp-graphql' );
			},
		];

		/**
		 * Loop through the post_stati
		 */
		foreach ( $post_stati as $status => $description ) {
			$values[ WPEnumType::get_safe_name( $status ) ] = [
				'description' => $description,
				'value'       => $status,
			];
		}

		register_graphql_enum_type(
			'MediaItemStatusEnum',
			[
				'description' => static function () {
					return __( 'Publication status for media items. Controls whether media is publicly accessible, private, or in another state.', 'wp-graphql' );
				},
				'values'      => $values,
			]
		);
	}
}
