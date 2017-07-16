<?php

namespace WPGraphQL\Type\MenuItem;

use GraphQLRelay\Relay;
use WPGraphQL\Type\MenuItem\Connection\MenuItemConnectionDefinition;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Types;

/**
 * Class MenuItemType
 *
 * @package WPGraphQL\Type
 */
class MenuItemType extends WPObjectType {

	/**
	 * Holds the type name
	 *
	 * @var string $type_name
	 */
	private static $type_name;

	/**
	 * This holds the field definitions
	 *
	 * @var array $fields
	 */
	private static $fields;

	/**
	 * MenuItemType constructor.
	 */
	public function __construct() {

		self::$type_name = 'menuItem';

		$config = [
			'name'        => self::$type_name,
			'description' => __( 'Navigation menu items are the individual items assigned to a menu. These are rendered as the links in a navigation menu.', 'wp-graphql' ),
			'fields'      => self::fields(),
		];

		parent::__construct( $config );

	}

	/**
	 * fields
	 *
	 * This defines the fields that make up the MenuLocationType
	 *
	 * @return array|\Closure|null
	 */
	private static function fields() {

		if ( null === self::$fields ) {

			self::$fields = function() {
				$fields = [
					'id'                    => [
						'type'        => Types::non_null( Types::id() ),
						'description' => __( 'ID of the nav menu item.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $menu_item ) {
							return ( ! empty( $menu_item->post_type ) && ! empty( $menu_item->ID ) ) ? Relay::toGlobalId( $menu_item->post_type, $menu_item->ID ) : null;
						},
					],
					self::$type_name . 'Id' => [
						'type'        => Types::int(),
						'description' => __( 'ID of the nav menu item.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $menu_item ) {
							return ! empty( $menu_item->ID ) ? $menu_item->ID : null;
						},
					],
					'childItems' => MenuItemConnectionDefinition::connection(),
					'title'                 => [
						'type'        => Types::string(),
						'description' => __( 'Title of the nav menu item. This is what is displayed visually in a menu as text.', 'wp-graphql' ),
					],
					'titleAttribute' => [
						'type' => Types::string(),
						'description' => __( 'The title attribute for the menu', 'wp-graphql' ),
						'resolve' => function( \WP_Post $menu_item ) {
							return ! empty( $menu_item->attr_title ) ? $menu_item->attr_title : null;
						},
					],
					'parentItem' => [
						'type' => Types::menu_item(),
						'description' => __( 'The parent menu item', 'wp-graphql' ),
						'resolve' => function( \WP_Post $menu_item ) {

							if ( ! empty( $menu_item->menu_item_parent ) ) {
								$parent_menu_post = get_post( $menu_item->menu_item_parent );
							}

							$parent_menu_item = ! empty( $parent_menu_post ) ? wp_setup_nav_menu_item( $parent_menu_post ) : null;
							return ! empty( $parent_menu_item ) ? $parent_menu_item : null;
						},
					],
					'connectedObjectType'            => [
						'type'        => Types::string(),
						'description' => __( 'The type relating the object being displayed in the type.', 'wp-graphql' ),
						'resolve' => function( \WP_Post $menu_item ) {
							return get_post_meta( $menu_item->ID, '_menu_item_object', true );
						},
					],
					'connectedObjectId'       => [
						'type'        => Types::id(),
						'description' => __( 'The ID of the object the menu item relates to.', 'wp-graphql' ),
						'resolve' => function( \WP_Post $menu_item ) {
							return get_post_meta( $menu_item->ID, '_menu_item_object_id', true );
						},
					],
					// @todo: add a union to resolve the actual object the menu links to as it could be a term, post_type, etc
					'target'                => [
						'type'        => Types::string(),
						'description' => __( 'Target attribute for the link.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $menu_item ) {
							return get_post_meta( $menu_item->ID, '_menu_item_target', true );
						},
					],
					'linkRelationship'      => [
						'type'        => Types::string(),
						'description' => __( 'Link relationship.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $menu_item ) {
							return get_post_meta( $menu_item->ID, '_menu_item_xfn', true );
						},
					],
					'url'                   => [
						'type'        => Types::string(),
						'description' => __( 'URL for the nav menu item.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $menu_item ) {
							$url = get_post_meta( $menu_item->ID, '_menu_item_url', true );
							if ( empty( $url ) ) {
								$post_id = get_post_meta( $menu_item->ID, '_menu_item_object_id', true );
								$url     = get_permalink( $post_id );
							}

							return $url;
						},
					],
				];

				return self::prepare_fields( $fields, self::$type_name );
			};

		} // End if().

		return ! empty( self::$fields ) ? self::$fields : null;

	}

}
