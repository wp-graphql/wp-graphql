<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Type\WPEnumType;

/**
 * Class MenuLocationEnumType
 *
 * @package WPGraphQL\Type\Enum
 */
class MenuLocationEnumType extends WPEnumType {

	/**
	 * This stores the values for the Enum
	 * @var array $values
	 * @access private
	 */
	private static $values;

	/**
	 * MenuLocationEnumType constructor.
	 * @access public
	 */
	public function __construct() {

		$config = [
			'name'        => 'menuLocation',
			'description' => __( 'Registered menu locations', 'wp-graphql' ),
			'values'      => self::values(),
		];

		parent::__construct( $config );

	}

	/**
	 * This configures the values to use for the Enum
	 * @return array
	 * @access private
	 */
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
				 * This formats the name by getting rid of spaces and non alphanumeric characters, and replacing them with underscores like so:
				 *
				 * Primary Nav => PRIMARY_NAV to be used in the Enum selection
				 */
				$formatted_name = strtoupper( preg_replace( '/[^A-Za-z0-9]/i', '_', $name ) );

				/**
				 * Add the menus to the enum_values array
				 */
				self::$values[ $name ] = [
					'name' => $formatted_name,
					'value' => $menu,
				];
			}
		}

		return self::$values;

	}

}
