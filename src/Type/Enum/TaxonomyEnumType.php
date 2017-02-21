<?php
namespace WPGraphQL\Type\Enum;

use GraphQL\Type\Definition\EnumType;

class TaxonomyEnumType extends EnumType {

	private static $values;

	public function __construct() {

		$config = [
			'name' => 'taxonomyEnum',
			'description' => __( 'Allowed taxonomies', 'wp-graphql' ),
			'values' => self::values(),
		];

		parent::__construct( $config );

	}

	private static function values() {

		if ( null === self::$values ) {

			/**
			 * Set an empty array
			 */
			self::$values = [];

			/**
			 * Get the allowed taxonomies
			 */
			$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies();

			/**
			 * Loop through the taxonomies and create an array
			 * of values for use in the enum type.
			 */
			foreach ( $allowed_taxonomies as $taxonomy ) {
				self::$values[ $taxonomy ] = [
					'name' => strtoupper( $taxonomy ),
					'value' => $taxonomy,
				];
			}
		}

		/**
		 * Return the $values
		 */
		return ! empty( self::$values ) ? self::$values : null;

	}

}