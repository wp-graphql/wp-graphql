<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Type\WPEnumType;
use WPGraphQL\Utils\Utils;

class ContentTypeEnum {

	/**
	 * Register the ContentTypeEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		$values = [];

		/**
		 * Get the allowed post types
		 *
		 * @var string[] $allowed_post_types
		 */
		$allowed_post_types = \WPGraphQL::get_allowed_post_types();

		/**
		 * Loop through the post types and create an array
		 * of values for use in the enum type.
		 */
		foreach ( $allowed_post_types as $allowed_post_type ) {
			$values[ WPEnumType::get_safe_name( $allowed_post_type ) ] = [
				'value'       => $allowed_post_type,
				'description' => __( 'The Type of Content object', 'wp-graphql' ),
			];
		}

		register_graphql_enum_type(
			'ContentTypeEnum',
			[
				'description' => __( 'Allowed Content Types', 'wp-graphql' ),
				'values'      => $values,
			]
		);

		/**
		 * Register a ContentTypesOf${taxonomyName}Enum for each taxonomy
		 *
		 * @var \WP_Taxonomy[] $allowed_taxonomies
		 */
		$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies( 'objects' );
		foreach ( $allowed_taxonomies as $tax_object ) {
			/**
			 * Loop through the taxonomy's object type and create an array
			 * of values for use in the enum type.
			 */
			$taxonomy_values = [];
			foreach ( $tax_object->object_type as $tax_object_type ) {
				// Skip object types that are not allowed by WPGraphQL
				if ( ! array_key_exists( $tax_object_type, $allowed_post_types ) ) {
					continue;
				}

				$taxonomy_values[ WPEnumType::get_safe_name( $tax_object_type ) ] = [
					'name'        => WPEnumType::get_safe_name( $tax_object_type ),
					'value'       => $tax_object_type,
					'description' => __( 'The Type of Content object', 'wp-graphql' ),
				];
			}

			if ( ! empty( $taxonomy_values ) ) {

				register_graphql_enum_type(
					'ContentTypesOf' . Utils::format_type_name( $tax_object->graphql_single_name ) . 'Enum',
					[
						'description' => sprintf( __( 'Allowed Content Types of the %s taxonomy.', 'wp-graphql' ), Utils::format_type_name( $tax_object->graphql_single_name ) ),
						'values'      => $taxonomy_values,
					]
				);
			}
		}

	}
}
