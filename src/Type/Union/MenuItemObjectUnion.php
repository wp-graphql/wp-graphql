<?php

namespace WPGraphQL\Type\Union;

use Exception;
use WPGraphQL\Model\Post;
use WPGraphQL\Model\Term;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class MenuItemObjectUnion
 *
 * @package WPGraphQL\Type\Union
 * @deprecated
 */
class MenuItemObjectUnion {

	/**
	 * Registers the Type
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ) {

		register_graphql_union_type(
			'MenuItemObjectUnion',
			[
				'typeNames'   => self::get_possible_types(),
				'description' => __( 'Deprecated in favor of MenuItemLinkeable Interface', 'wp-graphql' ),
				'resolveType' => function( $object ) use ( $type_registry ) {
					// Post object
					if ( $object instanceof Post && isset( $object->post_type ) && ! empty( $object->post_type ) ) {
						/** @var \WP_Post_Type $post_type_object */
						$post_type_object = get_post_type_object( $object->post_type );

						return $type_registry->get_type( $post_type_object->graphql_single_name );
					}

					// Taxonomy term
					if ( $object instanceof Term && ! empty( $object->taxonomyName ) ) {
						/** @var \WP_Taxonomy $taxonomy_object */
						$taxonomy_object = get_taxonomy( $object->taxonomyName );

						return $type_registry->get_type( $taxonomy_object->graphql_single_name );
					}

					return $object;
				},
			]
		);
	}

	/**
	 * Returns a list of possible types for the union
	 *
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

		return $possible_types;
	}
}

