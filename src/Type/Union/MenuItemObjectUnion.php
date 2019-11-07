<?php

namespace WPGraphQL\Type\Union;

use WPGraphQL\Model\MenuItem;
use WPGraphQL\Model\Post;
use WPGraphQL\Model\Term;
use WPGraphQL\Registry\TypeRegistry;

class MenuItemObjectUnion {

	/**
	 * Registers the Type
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @access public
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {

		register_graphql_union_type(
			'MenuItemObjectUnion',
			[
				'typeNames'   => self::get_possible_types(),
				'resolveType' => function( $object ) use ( $type_registry ) {

					// Custom link / menu item
					if ( $object instanceof MenuItem ) {
						return $type_registry->get_type( 'MenuItem' );
					}

					// Post object
					if ( $object instanceof Post && ! empty( $object->post_type ) ) {
						$post_type_object = get_post_type_object( $object->post_type );

						return $type_registry->get_type( $post_type_object->graphql_single_name );
					}

					// Taxonomy term
					if ( $object instanceof Term && ! empty( $object->taxonomyName ) ) {
						$tax_object = get_taxonomy( $object->taxonomyName );

						return $type_registry->get_type( $tax_object->graphql_single_name );
					}

					return $object;
				},
			]
		);
	}

	/**
	 * Returns a list of possible types for the union
	 *
	 * @access public
	 * @return array
	 */
	public static function get_possible_types() {

		/**
		 * The possible types for MenuItems should be just the TermObjects and PostTypeObjects that are
		 * registered to "show_in_graphql" and "show_in_nav_menus"
		 */
		$args = [
			'show_in_nav_menus' => true,
		];

		$possible_types = [];

		// Add post types that are allowed in WPGraphQL.
		foreach ( \WPGraphQL::get_allowed_post_types( $args ) as $type ) {
			$post_type_object = get_post_type_object( $type );
			if ( isset( $post_type_object->graphql_single_name ) ) {
				$possible_types[] = $post_type_object->graphql_single_name;
			}
		}

		// Add taxonomies that are allowed in WPGraphQL.
		foreach ( get_taxonomies( $args ) as $type ) {
			$tax_object = get_taxonomy( $type );
			if ( isset( $tax_object->graphql_single_name ) ) {
				$possible_types[] = $tax_object->graphql_single_name;
			}
		}

		// Add the custom link type (which is just a menu item).
		$possible_types[] = 'MenuItem';

		return $possible_types;
	}
}

