<?php

namespace WPGraphQL\Type\ObjectType;

use WPGraphQL\Model\Menu as MenuModel;

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
				'description' => static function () {
					return __( 'Collections of navigation links. Menus can be assigned to designated locations and used to build site navigation structures.', 'wp-graphql' );
				},
				'interfaces'  => [ 'Node', 'DatabaseIdentifier' ],
				'model'       => MenuModel::class,
				'fields'      => static function () {
					return [
						'id'           => [
							'description' => static function () {
								return __( 'The globally unique identifier of the nav menu object.', 'wp-graphql' );
							},
						],
						'count'        => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'The number of items in the menu', 'wp-graphql' );
							},
						],
						'menuId'       => [
							'type'              => 'Int',
							'description'       => static function () {
								return __( 'WP ID of the nav menu.', 'wp-graphql' );
							},
							'deprecationReason' => static function () {
								return __( 'Deprecated in favor of the databaseId field', 'wp-graphql' );
							},
						],
						'name'         => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Display name of the menu. Equivalent to WP_Term->name.', 'wp-graphql' );
							},
						],
						'slug'         => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The url friendly name of the menu. Equivalent to WP_Term->slug', 'wp-graphql' );
							},
						],
						'isRestricted' => [
							'type'        => 'Boolean',
							'description' => static function () {
								return __( 'Whether the object is restricted from the current viewer', 'wp-graphql' );
							},
						],
						'locations'    => [
							'type'        => [
								'list_of' => 'MenuLocationEnum',
							],
							'description' => static function () {
								return __( 'The locations a menu is assigned to', 'wp-graphql' );
							},
						],
					];
				},
			]
		);
	}
}
