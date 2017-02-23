<?php
namespace WPGraphQL\Type\Enum;

use WPGraphQL\Type\WPEnumType;

/**
 * Class PostStatusEnumType
 *
 * This defines an EnumType with allowed post stati that are registered to WordPress.
 *
 * @package WPGraphQL\Type\Enum
 * @since 0.0.5
 */
class PostStatusEnumType extends WPEnumType {

	/**
	 * This holds the enum values array
	 * @var array $values
	 */
	private static $values;

	public function __construct() {
		$description = __( 'The status of the object.', 'wp-graphql' );
		parent::__construct( 'status', self::values(), $description );
	}

	/**
	 * values
	 * Creates a list of post_stati that can be used to query by.
	 * @return array
	 */
	private static function values() {

		/**
		 * Set the default, if no values are built dynamically
		 * @since 0.0.5
		 */
		self::$values = [
			'name' => 'PUBLISH',
			'value' => 'publish',
		];

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
				self::$values[ $status ] = [
					'name' => strtoupper( preg_replace( '/[^A-Za-z0-9]/i', '_', $status ) ),
					'description' => sprintf( __( 'Objects with the %1$s status', 'wp-graphql' ), $status ),
					'value' => $status,
				];
			}
		}

		/**
		 * Return the $values
		 */
		return self::$values;

	}

}
