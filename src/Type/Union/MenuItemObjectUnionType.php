<?php
/**
 * MenuItemObjectUnionType
 */

namespace WPGraphQL\Type\Union;

use GraphQL\Type\Definition\UnionType;
use WPGraphQL\Types;

/**
 * Class MenuItemObjectUnionType
 *
 * Navigation menus comprise menu items that reference an object, which can be
 * a post object, a taxonomy term object, or a custom link.
 */
class MenuItemObjectUnionType extends UnionType {
	/**
	 * An array of the possible types that can be resolved by this union.
	 *
	 * @var array
	 */
	private static $possible_types;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$config = [
			'name'  => 'MenuItemObjectUnion',
			'types' => self::get_possible_types(),
			'resolveType' => function( $object ) {
				// Custom link / menu item
				if ( $object instanceof \WP_Post && 'nav_menu_item' === $object->post_type ) {
					return Types::menu_item();
				}

				// Post object
				if ( $object instanceof \WP_Post && ! empty( $object->post_type ) ) {
					return Types::post_object( $object->post_type );
				}

				// Taxonomy term
				if ( $object instanceof \WP_Term && ! empty( $object->taxonomy ) ) {
					return Types::term_object( $object->taxonomy );
				}

				return null;
			},
		];

		parent::__construct( $config );
	}

	/**
	 * This defines the possible types that can be resolved by this union
	 *
	 * @return array An array of possible types that can be resolved by the union
	 * @since 0.0.5
	 */
	public static function get_possible_types() {
		if ( is_array( self::$possible_types ) ) {
			return self::$possible_types;
		}

		// We could restrict this further using `show_in_nav_menus`, but it's of
		// questionable utility since we'd only be creating more work for those
		// that want to implement custom resolution of menu items.
		$args = [
			'show_in_graphql'   => true,
		];

		self::$possible_types = [];

		// Add post types that are allowed in WPGraphQL.
		foreach ( get_post_types( $args ) as $type ) {
			self::$possible_types[ $type ] = Types::post_object( $type );
		}

		// Add taxonomies that are allowed in WPGraphQL.
		foreach ( get_taxonomies( $args ) as $type ) {
			self::$possible_types[ $type ] = Types::term_object( $type );
		}

		// Add the custom link type (which is just a menu item).
		self::$possible_types['MenuItem'] = Types::menu_item();

		/**
		 * Filter the possible types.
		 *
		 * @param array $possible_types An array of possible types that can be resolved for the union.
		 */
		self::$possible_types = apply_filters( 'graphql_menu_item_union_possible_types', self::$possible_types );

		return self::$possible_types;
	}
}
