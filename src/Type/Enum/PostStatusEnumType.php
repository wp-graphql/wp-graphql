<?php
namespace WPGraphQL\Type\Enum;

use GraphQL\Type\Definition\EnumType;

class PostStatusEnumType extends EnumType {

	private static $values;

	public function __construct() {

		$config = [
			'name' => 'status',
			'values' => self::values(),
		];

		parent::__construct( $config );

	}

	/**
	 * values
	 *
	 * Creates a list of post_stati that can be used to query by.
	 *
	 * @return array
	 */
	private static function values() {

		/**
		 * Get the dynamic list of post_stati
		 */
		$post_stati = get_post_stati();

		/**
		 * If there are $post_stati, create the $values based on them
		 */
		if ( ! empty( $post_stati ) && is_array( $post_stati ) ) {
			/**
			 * Reset the array
			 */
			self::$values = [];
			/**
			 * Loop through the post_stati
			 */
			foreach ( $post_stati as $status ) {
				self::$values[] = [
					'name'  => strtoupper( preg_replace( '/[^A-Za-z0-9]/i', '_', $status ) ),
					'description' => sprintf( __( 'Objects with the %2$s status', 'wp-graphql' ), $status ),
					'value' => $status,
				];
			}
		}

		/**
		 * Return the $values
		 */
		return ! empty( self::$values ) ? self::$values : null;

	}

}