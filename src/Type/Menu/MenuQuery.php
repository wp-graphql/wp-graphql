<?php
namespace WPGraphQL\Type\Menu;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Type\WPEnumType;
use WPGraphQL\Types;

/**
 * Class MenuQuery
 *
 * @package WPGraphQL\Type\Menu
 */
class MenuQuery {

	/**
	 * Holds the root_query field definition
	 * @var array $root_query
	 */
	private static $root_query;

	/**
	 * Holds the definition for the menu enum field
	 * @var \WP_Enum_Type $menu_enum
	 */
	private static $menu_enum;

	/**
	 * Holds the array of values for the menu_enum
	 * @var array $menu_enum_values
	 */
	private static $menu_enum_values;

	/**
	 * Method that returns the root query field definition for the menu query
	 * @return array
	 */
	public static function root_query() {

		if ( null === self::$root_query ) :

			self::$root_query = array(
				'type'        => Types::menu(),
				'description' => __( 'Retrieve a menu by providing a Menu name, ID or slug', 'wp-graphql' ),
				'args'        => [
					'location' => [
						'type' => self::menu_enum(),
						'description' => __( 'The registered menu location for the menu being queried', 'wp-graphql' ),
					],
				],
				'resolve'     => function( $value, $args, AppContext $context, ResolveInfo $info ) {
					$theme_locations = get_nav_menu_locations();
					$location = ! empty( $args['location'] ) ? $args['location'] : null;
					$menu_object = ! empty( $location ) ? wp_get_nav_menu_object( $theme_locations[ $location ] ) : null;
					$menu = ! empty( $menu_object ) && is_object( $menu_object ) ? $menu_object : null;
					return ( ! empty( $menu ) && 'nav_menu' === $menu->taxonomy ) ? $menu : null;
				},
			);
		endif;

		return self::$root_query;

	}

	/**
	 * Rerturns the definition of the menu enum type
	 * @return \WP_Enum_Type|WPEnumType
	 */
	public static function menu_enum() {
		if ( null === self::$menu_enum ) {
			self::$menu_enum = new WPEnumType( [
				'name'   => 'location',
				'values' => self::menu_enum_values(),
			] );
		}
		return self::$menu_enum;
	}

	/**
	 * Returns an array of values for the menu_enum type
	 * @return array
	 */
	private static function menu_enum_values() {

		if ( empty( self::$menu_enum_values ) ) {

			/**
			 * Get the registered nav menus
			 */
			$registered_menus = get_registered_nav_menus();

			/**
			 * If there are any registered menus, use them to populate the ENUM
			 */
			if ( ! empty( $registered_menus ) ) {
				foreach ( $registered_menus as $menu => $name ) {

					/**
					 * Convert the menu name to the Enum format we want
					 *
					 * ex:
					 * Primary Nav -> PRIMARY_NAV
					 */
					$name = str_ireplace( '-', '_', $name );
					$name = str_ireplace( ' ', '_', $name );
					$name = strtoupper( $name );

					/**
					 * Add the menus to the enum_values array
					 */
					self::$menu_enum_values[ $name ] = $menu;
				}
			} else {
				/**
				 * Output "NO_MENUS_REGISTERED" as the enum option for the menu location option when there are no
				 * registered menus
				 */
				self::$menu_enum_values['NO_MENUS_REGISTERED'] = 'none';
			}
		} // End if().

		return ! empty( self::$menu_enum_values ) ? self::$menu_enum_values : [];
	}

}
