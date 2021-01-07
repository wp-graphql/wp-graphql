<?php

namespace WPGraphQL\Type\Object;

class Menu {

	/**
	 * Register the Menu object type
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_object_type(
			'Menu',
			[
				'description' => __( 'Menus are the containers for navigation items. Menus can be assigned to menu locations, which are typically registered by the active theme.', 'wp-graphql' ),
				'interfaces'  => [ 'Node', 'DatabaseIdentifier' ],
				'fields'      => [
					'id'           => [
						'description' => __( 'The globally unique identifier of the nav menu object.', 'wp-graphql' ),
					],
					'count'        => [
						'type'        => 'Int',
						'description' => __( 'The number of items in the menu', 'wp-graphql' ),
					],
					'menuId'       => [
						'type'              => 'Int',
						'description'       => __( 'WP ID of the nav menu.', 'wp-graphql' ),
						'deprecationReason' => __( 'Deprecated in favor of the databaseId field', 'ID' ),
					],
					'name'         => [
						'type'        => 'String',
						'description' => esc_html__( 'Display name of the menu. Equivalent to WP_Term->name.', 'wp-graphql' ),
					],
					'slug'         => [
						'type'        => 'String',
						'description' => esc_html__( 'The url friendly name of the menu. Equivalent to WP_Term->slug', 'wp-graphql' ),
					],
					'isRestricted' => [
						'type'        => 'Boolean',
						'description' => __( 'Whether the object is restricted from the current viewer', 'wp-graphql' ),
					],
					'locations'    => [
						'type' => [
							'list_of'     => 'MenuLocationEnum',
							'description' => __( 'The locations a menu is assigned to', 'wp-graphql' ),
						],
					],
				],
			]
		);
	}
}
