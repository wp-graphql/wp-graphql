<?php
namespace WPGraphQL\Type;

use WPGraphQL\Model\MenuItem;
use WPGraphQL\Model\Post;
use WPGraphQL\Model\Term;
use WPGraphQL\TypeRegistry;
use WPGraphQL\Types;

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
	$possible_types[] = Types::post_object( $type );
}

// Add taxonomies that are allowed in WPGraphQL.
foreach ( get_taxonomies( $args ) as $type ) {
	$possible_types[] = Types::term_object( $type );
}

// Add the custom link type (which is just a menu item).
$possible_types['MenuItem'] = TypeRegistry::get_type( 'MenuItem' );

register_graphql_union_type(
	'MenuItemObjectUnion',
	[
		'types'       => $possible_types,
		'resolveType' => function ( $object ) {

			// Custom link / menu item
			if ( $object instanceof MenuItem ) {
				return TypeRegistry::get_type( 'MenuItem' );
			}

			// Post object
			if ( $object instanceof Post && ! empty( $object->post_type ) ) {
				return Types::post_object( $object->post_type );
			}

			// Taxonomy term
			if ( $object instanceof Term && ! empty( $object->taxonomyName ) ) {
				return Types::term_object( $object->taxonomyName );
			}

			return null;
		},
	]
);
