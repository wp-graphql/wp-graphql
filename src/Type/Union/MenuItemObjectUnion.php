<?php
namespace WPGraphQL\Type;

use WPGraphQL\TypeRegistry;
use WPGraphQL\Types;

/**
 * The possible types for MenuItems should be just the TermObjects and PostTypeObjects that are
 * registered to "show_in_graphql" and "show_in_nav_menus"
 */
$args = [
	'show_in_graphql' => true,
	'show_in_nav_menus' => true,
];

$possible_types = [];

// Add post types that are allowed in WPGraphQL.
foreach ( get_post_types( $args ) as $type ) {
	$possible_types[ $type ] = Types::post_object( $type );
}

// Add taxonomies that are allowed in WPGraphQL.
foreach ( get_taxonomies( $args ) as $type ) {
	$possible_types[ $type ] = Types::term_object( $type );
}

// Add the custom link type (which is just a menu item).
$possible_types['MenuItem'] = TypeRegistry::get_type( 'MenuItem' );

register_graphql_union_type( 'MenuItemObjectUnion', [
	'types'       => $possible_types,
	'resolveType' => function ( $object ) {
		// Custom link / menu item
		if ( $object instanceof \WP_Post && 'nav_menu_item' === $object->post_type ) {
			return TypeRegistry::get_type( 'MenuItem' );
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
] );
