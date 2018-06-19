<?php

namespace WPGraphQL\Type\Enum;

use WPGraphQL\Type\WPEnumType;

/**
 * Class MenuLocationEnumType
 *
 * @package WPGraphQL\Type\Enum
 * @since   0.0.30
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
	 * Generate a safe / sanitized name from a menu location slug.
	 *
	 * @param  string $value Enum value.
	 * @return string
	 */
	private static function get_safe_name( $value ) {
		$safe_name = strtoupper( preg_replace( '#[^A-z0-9]#', '_', $value ) );

		// Enum names must start with a letter or underscore.
		if ( ! preg_match( '#^[_a-zA-Z]#', $value ) ) {
			return '_' . $safe_name;
		}

		return $safe_name;
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
		 * Loop through the registered nav menu locations for the current theme
		 * and create an array of values for use in the enum type.
		 */
		self::$values = [];
		foreach ( array_keys( get_nav_menu_locations() ) as $location ) {
			self::$values[ self::get_safe_name( $location ) ] = [
				'value' => $location,
			];
		}

		/**
		 * Enums cannot be empty, so provide a dummy location if none are
		 * registered for this theme.
		 */
		if ( empty( self::$values ) ) {
			self::$values['EMPTY'] = [
				'value' => 'Empty menu location',
			];
		}

		return self::$values;
	}

}