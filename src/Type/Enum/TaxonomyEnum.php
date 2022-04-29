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
		/** @var \WP_Taxonomy[] $allowed_taxonomies */
		$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies( 'objects' );

		$values = [];

		/**
		 * Loop through the taxonomies and create an array
		 * of values for use in the enum type.
		 */

		foreach ( $allowed_taxonomies as $taxonomy_object ) {
			if ( ! isset( $values[ WPEnumType::get_safe_name( $taxonomy_object->graphql_single_name ) ] ) ) {
				$values[ WPEnumType::get_safe_name( $taxonomy_object->graphql_single_name ) ] = [
					'value'       => $taxonomy_object->name,
					'description' => sprintf( __( 'Taxonomy enum %s', 'wp-graphql' ), $taxonomy_object->name ),
				];
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
