<?php
namespace WPGraphQL\Type\Menu;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
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
						'type' => Types::menu_location_enum(),
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

}
