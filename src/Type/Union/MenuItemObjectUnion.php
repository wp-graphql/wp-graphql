<?php

namespace WPGraphQL\Type\Union;

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
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_union_type(
			'MenuItemObjectUnion',
			[
				'typeNames'   => self::get_possible_types(),
				'description' => static function () {
					return __( 'Deprecated in favor of MenuItemLinkeable Interface', 'wp-graphql' );
				},
				'resolveType' => static function ( $obj ) use ( $type_registry ) {
					_doing_it_wrong( 'MenuItemObjectUnion', esc_attr__( 'The MenuItemObjectUnion GraphQL type is deprecated in favor of MenuItemLinkeable Interface', 'wp-graphql' ), '0.10.3' );
					// Post object
					if ( $obj instanceof Post && isset( $obj->post_type ) && ! empty( $obj->post_type ) ) {
						/** @var \WP_Post_Type $post_type_object */
						$post_type_object = get_post_type_object( $obj->post_type );

						return $type_registry->get_type( $post_type_object->graphql_single_name );
					}

					// Taxonomy term
					if ( $obj instanceof Term && ! empty( $obj->taxonomyName ) ) {
						/** @var \WP_Taxonomy $tax_object */
						$tax_object = get_taxonomy( $obj->taxonomyName );

						return $type_registry->get_type( $tax_object->graphql_single_name );
					}

					return $obj;
				},
			]
		);
	}

	/**
	 * Returns a list of possible types for the union
	 *
	 * @return string[]
	 */
	public static function get_possible_types() {

		/**
		 * The possible types for MenuItems should be just the TermObjects and PostTypeObjects that are
		 * registered to "show_in_graphql" and "show_in_nav_menus"
		 */
		$args = [
			'show_in_nav_menus' => true,
			'graphql_kind'      => 'object',
		];

		$possible_types = [];

		/**
		 * Add post types that are allowed in WPGraphQL.
		 */
		foreach ( \WPGraphQL::get_allowed_post_types( 'objects', $args ) as $post_type_object ) {
			if ( isset( $post_type_object->graphql_single_name ) ) {
				$possible_types[] = $post_type_object->graphql_single_name;
			}
		}

		// Add taxonomies that are allowed in WPGraphQL.
		foreach ( \WPGraphQL::get_allowed_taxonomies( 'objects', $args ) as $tax_object ) {
			if ( isset( $tax_object->graphql_single_name ) ) {
				$possible_types[] = $tax_object->graphql_single_name;
			}
		}

		return $possible_types;
	}
}
