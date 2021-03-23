<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Type\WPEnumType;

class TaxonomyEnum {

	/**
	 * Register the TaxonomyEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies();

		$values = [];

		/**
		 * Loop through the taxonomies and create an array
		 * of values for use in the enum type.
		 */
		if ( ! empty( $allowed_taxonomies ) && is_array( $allowed_taxonomies ) ) {
			foreach ( $allowed_taxonomies as $allowed_taxonomy ) {
				/** @var \WP_Taxonomy $taxonomy_object */
				$taxonomy_object = get_taxonomy( $allowed_taxonomy );
				if ( ! isset( $values[ WPEnumType::get_safe_name( $taxonomy_object->graphql_single_name ) ] ) ) {
					$values[ WPEnumType::get_safe_name( $taxonomy_object->graphql_single_name ) ] = [
						'value'       => $allowed_taxonomy,
						'description' => sprintf( __( 'Taxonomy enum %s', 'wp-graphql' ), $allowed_taxonomy ),
					];
				}
			}
		}

		register_graphql_enum_type(
			'TaxonomyEnum',
			[
				'description' => __( 'Allowed taxonomies', 'wp-graphql' ),
				'values'      => $values,
			]
		);

	}
}
