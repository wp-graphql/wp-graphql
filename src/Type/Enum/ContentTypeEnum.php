<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Type\WPEnumType;

class ContentTypeEnum {

	/**
	 * Register the ContentTypeEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		$values = [];

		/**
		 * Get the allowed taxonomies
		 */
		$allowed_post_types = get_post_types( [
			'show_in_graphql' => true,
		] );

		/**
		 * Loop through the taxonomies and create an array
		 * of values for use in the enum type.
		 */
		if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {
			foreach ( $allowed_post_types as $allowed_post_type ) {

				if ( $allowed_post_type instanceof \WP_Post_Type ) {
					$allowed_post_type = $allowed_post_type->name;
				}

				$values[ WPEnumType::get_safe_name( $allowed_post_type ) ] = [
					'value'       => $allowed_post_type,
					'description' => __( 'The Type of Content object', 'wp-graphql' ),
				];
			}
		}

		register_graphql_enum_type(
			'ContentTypeEnum',
			[
				'description' => __( 'Allowed Content Types', 'wp-graphql' ),
				'values'      => $values,
			]
		);
	}
}
