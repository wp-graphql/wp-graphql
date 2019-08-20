<?php
namespace WPGraphQL\Type;

use WPGraphQL\Model\MenuItem;
use WPGraphQL\Model\Post;
use WPGraphQL\Model\Term;
use WPGraphQL\Registry\TypeRegistry;

add_action( 'init_type_registry', function( TypeRegistry $type_registry ) {

	/**
	 * The possible types for MenuItems should be just the TermObjects and PostTypeObjects that are
	 * registered to "show_in_graphql" and "show_in_nav_menus"
	 */
	$args = [
		'show_in_graphql'   => true,
		'show_in_nav_menus' => true,
	];

	$possible_types = [];

	// Add post types that are allowed in WPGraphQL.
	foreach ( get_post_types( $args ) as $type ) {
		$post_type_object = get_post_type_object( $type );
		if ( isset( $post_type_object->graphql_single_name ) ) {
			$possible_types[] = $type_registry->get_type( $post_type_object->graphql_single_name );
		}
	}

	// Add taxonomies that are allowed in WPGraphQL.
	foreach ( get_taxonomies( $args ) as $type ) {
		$tax_object = get_taxonomy( $type );
		if ( isset( $tax_object->graphql_single_name ) ) {
			$possible_types[] = $type_registry->get_type( $tax_object->graphql_single_name );
		}
	}

	// Add the custom link type (which is just a menu item).
	$possible_types['MenuItem'] = $type_registry->get_type( 'MenuItem' );


	register_graphql_union_type(
		'MenuItemObjectUnion',
		[
			'types'       => $possible_types,
			'resolveType' => function ( $object ) use ( $type_registry ) {

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

				return null;
			},
		]
	);

} );

