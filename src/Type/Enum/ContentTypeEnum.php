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

		/**
		 * Register a ContentTypesOf${taxonomyName}Enum for each taxonomy
		 */
		$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies();
		if ( ! empty( $allowed_taxonomies ) && is_array( $allowed_taxonomies ) ) {
			foreach ( $allowed_taxonomies as $taxonomy ) {
				/** @var \WP_Taxonomy $taxonomy_object */
				$taxonomy_object = get_taxonomy( $taxonomy );

				/**
				 * Loop through the taxonomy's object type and create an array
				 * of values for use in the enum type.
				 */
				$taxonomy_values = [];
				foreach ( $taxonomy_object->object_type as $taxonomy_object_type ) {
					// Skip object types that are not allowed by WPGraphQL
					if ( ! array_key_exists( $taxonomy_object_type, $allowed_post_types ) ) {
						continue;
					}

					$taxonomy_values[ WPEnumType::get_safe_name( $taxonomy_object_type ) ] = [
						'name'        => WPEnumType::get_safe_name( $taxonomy_object_type ),
						'value'       => $taxonomy_object_type,
						'description' => __( 'The Type of Content object', 'wp-graphql' ),
					];
				}

				if ( ! empty( $taxonomy_values ) ) {

					register_graphql_enum_type(
						'ContentTypesOf' . Utils::format_type_name( $taxonomy_object->graphql_single_name ) . 'Enum',
						[
							'description' => sprintf( __( 'Allowed Content Types of the %s taxonomy.', 'wp-graphql' ), Utils::format_type_name( $taxonomy_object->graphql_single_name ) ),
							'values'      => $taxonomy_values,
						]
					);
				}
			}
		}

	}
}
