<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Type\WPEnumType;

class MediaItemStatusEnum {
	public static function register_type() {
		$values = [];

		$post_stati = [
			'inherit',
			'private',
			'trash',
			'auto-draft',
		];

		if ( ! empty( $post_stati ) && is_array( $post_stati ) ) {
			/**
			 * Reset the array
			 */
			$values = [];
			/**
			 * Loop through the post_stati
			 */
			foreach ( $post_stati as $status ) {

				$values[ WPEnumType::get_safe_name( $status ) ] = [
					'description' => sprintf( __( 'Objects with the %1$s status', 'wp-graphql' ), $status ),
					'value'       => $status,
				];
			}
		}

		register_graphql_enum_type(
			'MediaItemStatusEnum',
			[
				'description' => __( 'The status of the media item object.', 'wp-graphql' ),
				'values'      => $values,
			]
		);

	}
}
