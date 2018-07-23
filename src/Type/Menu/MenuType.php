<?php

namespace WPGraphQL\Type\Menu;

use GraphQLRelay\Relay;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Types;
use WPGraphQL\Type\MenuItem\Connection\MenuItemConnectionDefinition;

/**
 * Class MenuType
 *
 * @package WPGraphQL\Type\Menu
 * @since   0.0.30
 */
class MenuType extends WPObjectType {

	/**
	 * Type name
	 *
	 * @var string $type_name
	 */
	private static $type_name = 'Menu';

	/**
	 * This holds the field definitions
	 *
	 * @var array $fields
	 */
	private static $fields;

	/**
	 * MenuType constructor.
	 */
	public function __construct() {
		$config = [
			'name'        => self::$type_name,
			'description' => __( 'Menus are the containers for navigation items. Menus can be assigned to menu locations, which are typically registered by the active theme.', 'wp-graphql' ),
			'fields'      => self::fields(),
		];

		parent::__construct( $config );
	}

	/**
	 * This defines the fields that make up the MenuType.
	 *
	 * @return array|\Closure|null
	 */
	private static function fields() {

		if ( null === self::$fields ) {

			self::$fields = function() {

				$fields = [
					'id'        => [
						'type'        => Types::non_null( Types::id() ),
						'description' => __( 'ID of the nav menu.', 'wp-graphql' ),
						'resolve'     => function( \WP_Term $menu ) {
							return ! empty( $menu->term_id ) ? Relay::toGlobalId( self::$type_name, $menu->term_id ) : null;
						},
					],
					'count'     => [
						'type'        => Types::int(),
						'description' => __( 'The number of items in the menu', 'wp-graphql' ),
					],
					'menuId'        => [
						'type'        => Types::int(),
						'description' => __( 'WP ID of the nav menu.', 'wp-graphql' ),
						'resolve'     => function( \WP_Term $menu ) {
							return ! empty( $menu->term_id ) ? $menu->term_id : null;
						},
					],
					'menuItems' => MenuItemConnectionDefinition::connection(),
					'name'      => [
						'type'        => Types::string(),
						'description' => esc_html__( 'Display name of the menu. Equivalent to WP_Term->name.', 'wp-graphql' ),
					],
					'slug'      => [
						'type'        => Types::string(),
						'description' => esc_html__( 'The url friendly name of the menu. Equivalent to WP_Term->slug', 'wp-graphql' ),
					],
				];

				return self::prepare_fields( $fields, self::$type_name );
			};

		} // End if().

		return ! empty( self::$fields ) ? self::$fields : null;

	}

}
