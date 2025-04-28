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
		$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies( 'objects' );

		$values = [];

		/**
		 * Loop through the taxonomies and create an array
		 * of values for use in the enum type.
		 */

		foreach ( $allowed_taxonomies as $tax_object ) {
			if ( ! isset( $values[ WPEnumType::get_safe_name( $tax_object->graphql_single_name ) ] ) ) {
				$values[ WPEnumType::get_safe_name( $tax_object->graphql_single_name ) ] = [
					'value'       => $tax_object->name,
					'description' => static function () use ( $tax_object ) {
						return sprintf(
							// translators: %s is the taxonomy name.
							__( 'Taxonomy enum %s', 'wp-graphql' ),
							$tax_object->name
						);
					},
				];
			}
		}

		register_graphql_enum_type(
			'TaxonomyEnum',
			[
				'description' => static function () {
					return __( 'Available classification systems for organizing content. Identifies the different taxonomy types that can be used for content categorization.', 'wp-graphql' );
				},
				'values'      => $values,
			]
		);
	}
}
