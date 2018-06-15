<?php

namespace WPGraphQL\Type\MenuItem;

use GraphQLRelay\Relay;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Types;
use WPGraphQL\Type\MenuItem\Connection\MenuItemConnectionDefinition;

/**
 * Class MenuItemType
 *
 * @package WPGraphQL\Type\MenuItem
 * @since   0.0.30
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

		self::$type_name = 'MenuItem';

		$config = [
			'name'        => self::$type_name,
			'description' => __( 'Navigation menu items are the individual items assigned to a menu. These are rendered as the links in a navigation menu.', 'wp-graphql' ),
			'fields'      => self::fields(),
		];

		parent::__construct( $config );

	}

	/**
	 * This defines the fields that make up the MenuItemType
	 *
	 * @return array|\Closure|null
	 */
	private static function fields() {

		if ( null === self::$fields ) {

			self::$fields = function() {
				$fields = [
					'id' => [
						'type'        => Types::non_null( Types::id() ),
						'description' => __( 'Relay ID of the menu item.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $menu_item ) {
							return ! empty( $menu_item->ID ) ? Relay::toGlobalId( self::$type_name, $menu_item->ID ) : null;
						},
					],
					'childItems' => MenuItemConnectionDefinition::connection(),
					'connectedObject' => [
						'type'        => Types::menu_item_object_union(),
						'description' => __( 'The object connected to this menu item.', 'wp-graphql' ),
						'resolve' => function( \WP_Post $menu_item, array $args, AppContext $context, ResolveInfo $info ) {
							$object_id   = intval( get_post_meta( $menu_item->ID, '_menu_item_object_id', true ) );
							$object_type = get_post_meta( $menu_item->ID, '_menu_item_type', true );

							// By default, resolve to the menu item itself. This is the
							// case for custom links.
							$resolved_object = $menu_item;

							switch ( $object_type ) {
								// Post object
								case 'post_type':
									$resolved_object = get_post( $object_id );
									break;

								// Taxonomy term
								case 'taxonomy':
									$resolved_object = get_term( $object_id );
									break;
							}

							/**
							 * Allow users to override how nav menu items are resolved.
							 * This is useful since we often add taxonomy terms to menus
							 * but would prefer to represent the menu item in other ways,
							 * e.g., a linked post object (or vice-versa).
							 *
							 * @param \WP_Post|\WP_Term $resolved_object Post or term connected to MenuItem
							 * @param array           $args            Array of arguments input in the field as part of the GraphQL query
							 * @param AppContext      $context         Object containing app context that gets passed down the resolve tree
							 * @param ResolveInfo     $info            Info about fields passed down the resolve tree
							 * @param int             $object_id       Post or term ID of connected object
							 * @param string          $object_type     Type of connected object ("post_type" or "taxonomy")
							 *
							 * @since 0.0.30
							 */
							return apply_filters(
								'graphql_resolve_menu_item',
								$resolved_object,
								$args,
								$context,
								$info,
								$object_id,
								$object_type
							);
						},
					],
					'cssClasses' => [
						'type'        => Types::list_of( Types::string() ),
						'description' => __( 'Class attribute for the menu item link', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $menu_item ) {

							// If all we have is a non-array or an array with one empty
							// string, return an empty array.
							if ( ! is_array( $menu_item->classes ) || empty( $menu_item->classes ) || empty( $menu_item->classes[0] ) ) {
								return [];
							}

							return $menu_item->classes;
						},
					],
					'description' => [
						'type'        => Types::string(),
						'description' => __( 'Description of the menu item.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $menu_item ) {
							return ( ! empty( $menu_item->description ) ) ? $menu_item->description : null;
						},
					],
					'label' => [
						'type'        => Types::string(),
						'description' => __( 'Label or title of the menu item.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $menu_item ) {
							return ( ! empty( $menu_item->title ) ) ? $menu_item->title : null;
						},
					],
					'linkRelationship' => [
						'type'        => Types::string(),
						'description' => __( 'Link relationship (XFN) of the menu item.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $menu_item ) {
							return ! empty( $menu_item->xfn ) ? $menu_item->xfn : null;
						},
					],
					'menuItemId' => [
						'type'        => Types::int(),
						'description' => __( 'WP ID of the menu item.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $menu_item ) {
							return ! empty( $menu_item->ID ) ? $menu_item->ID : null;
						},
					],
					'target' => [
						'type'        => Types::string(),
						'description' => __( 'Target attribute for the menu item link.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $menu_item ) {
							return ! empty( $menu_item->target ) ? $menu_item->target : null;
						},
					],
					'title' => [
						'type'        => Types::string(),
						'description' => __( 'Title attribute for the menu item link', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $menu_item ) {
							return ( ! empty( $menu_item->attr_title ) ) ? $menu_item->attr_title : null;
						},
					],
					'url' => [
						'type'        => Types::string(),
						'description' => __( 'URL or destination of the menu item.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $menu_item ) {
							return ! empty( $menu_item->url ) ? $menu_item->url : null;
						},
					],
				];

				return self::prepare_fields( $fields, self::$type_name );
			};

		} // End if().

		return ! empty( self::$fields ) ? self::$fields : null;

	}

}
