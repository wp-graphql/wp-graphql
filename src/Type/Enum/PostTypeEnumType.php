<?php
namespace WPGraphQL\Type\Enum;

use GraphQL\Type\Definition\EnumType;

class PostTypeEnumType extends EnumType {

	private static $values;

	public function __construct() {

		$config = [
			'name' => 'postTypeEnum',
			'description' => __( 'Allowed Post Types', 'wp-graphql' ),
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
			$allowed_post_types = \WPGraphQL::get_allowed_post_types();

			/**
			 * Loop through the taxonomies and create an array
			 * of values for use in the enum type.
			 */
			foreach ( $allowed_post_types as $post_type ) {
				self::$values[ $post_type ] = [
					'name' => strtoupper( $post_type ),
					'value' => $post_type,
				];
			}
		}

		/**
		 * Return the $values
		 */
		return ! empty( self::$values ) ? self::$values : null;

	}

}