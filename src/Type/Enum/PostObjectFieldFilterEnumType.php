<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Type\WPEnumType;

/**
 * Class PostObjectFieldFilterEnumType
 *
 * This defines an EnumType with allowed WordPress filters that can be applied
 * to post field data.
 *
 * @package WPGraphQL\Type\Enum
 * @since   0.0.18
 */
class PostObjectFieldFilterEnumType extends WPEnumType {

	/**
	 * This holds the enum values array.
	 *
	 * @var array $values
	 */
	private static $values;

	public function __construct() {
		$config = [
			'name'        => 'postObjectFieldFilter',
			'description' => __( 'The allowed WordPress filters on post field data.', 'wp-graphql' ),
			'values'      => self::values(),
		];
		parent::__construct( $config );
	}

	/**
	 * Creates a list of filters that can be used to filter post field data.
	 *
	 * @return array
	 */
	private static function values() {

		if ( null === self::$values ) {

			/**
			 * Provide some default filters that are in WP Core.
			 *
			 * @since 0.0.18
			 */
			self::$values = [
				'the_content' => [
					'name'  => 'THE_CONTENT',
					'description' => __( 'WordPress Core the_content filter', 'wp-graphql' ),
					'value' => 'the_content',
				],
				'get_the_excerpt' => [
					'name'  => 'GET_THE_EXCERPT',
					'description' => __( 'WordPress Core get_the_excerpt filter', 'wp-graphql' ),
					'value' => 'get_the_excerpt',
				],
				'the_excerpt' => [
					'name'  => 'THE_EXCERPT',
					'description' => __( 'WordPress Core the_excerpt filter', 'wp-graphql' ),
					'value' => 'the_excerpt',
				],
				'the_title' => [
					'name'  => 'THE_TITLE',
					'description' => __( 'WordPress Core the_title filter', 'wp-graphql' ),
					'value' => 'the_title',
				],
			];

		}

		/**
		 * Return the $values
		 */
		return self::$values;
	}
}
