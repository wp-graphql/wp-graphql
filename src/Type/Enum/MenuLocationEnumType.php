<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Type\WPEnumType;

class MenuLocationEnumType extends WPEnumType {

	private static $values;

	public function __construct() {

		$config = [
			'name'        => 'menuLocation',
			'description' => __( 'Registered menu locations', 'wp-graphql' ),
			'values'      => self::values(),
		];

		parent::__construct( $config );

	}

	private static function values() {

		/**
		 * Set an empty array
		 */
		self::$values = [];

		/**
		 * Get the allowed taxonomies
		 */
		$registered_menus = get_registered_nav_menus();

		/**
		 * Loop through the taxonomies and create an array
		 * of values for use in the enum type.
		 */
		if ( ! empty( $registered_menus ) ) {
			foreach ( $registered_menus as $menu => $name ) {

				/**
				 * Add the menus to the enum_values array
				 */
				self::$values[ $name ] = [
					'name' => strtoupper( preg_replace( '/[^A-Za-z0-9]/i', '_', $name ) ),
					'value' => $menu,
				];
			}
		}

		return self::$values;

	}

}
