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
	 *
	 * @var array $values
	 */
	private static $values;

	/**
	 * MenuLocationEnumType constructor.
	 */
	public function __construct() {

		$config = [
			'name'        => 'MenuLocation',
			'description' => __( 'Registered menu locations', 'wp-graphql' ),
			'values'      => self::values(),
		];

		parent::__construct( $config );
	}

	/**
	 * This configures the values to use for the Enum.
	 *
	 * @return array
	 */
	private static function values() {
		if ( is_array( self::$values ) ) {
			return self::$values;
		}

		/**
		 * Loop through the registered nav menu locations and create an array of
		 * values for use in the enum type. The location slug is already formatted
		 * to our liking (alphanumerics and underscores) so we simply need to
		 * uppercase it.
		 */
		self::$values = [];
		foreach ( array_keys( get_registered_nav_menus() ) as $location ) {
			self::$values[ strtoupper( $location ) ] = [
				'value' => $location,
			];
		}

		return self::$values;
	}

}