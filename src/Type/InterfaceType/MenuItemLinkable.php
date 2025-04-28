<?php

namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Model\Post;
use WPGraphQL\Model\Term;
use WPGraphQL\Registry\TypeRegistry;

class MenuItemLinkable {

	/**
	 * Registers the MenuItemLinkable Interface Type
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry Instance of the WPGraphQL Type Registry
	 *
	 * @throws \Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {
		register_graphql_interface_type(
			'MenuItemLinkable',
			[
				'description' => static function () {
					return __( 'Content that can be referenced by navigation menu items. Provides the essential fields needed to create links within navigation structures.', 'wp-graphql' );
				},
				'interfaces'  => [ 'Node', 'UniformResourceIdentifiable', 'DatabaseIdentifier' ],
				'fields'      => [],
				'resolveType' => static function ( $node ) use ( $type_registry ) {
					switch ( true ) {
						case $node instanceof Post && isset( $node->post_type ):
							/** @var \WP_Post_Type $post_type_object */
							$post_type_object = get_post_type_object( $node->post_type );
							$type             = $type_registry->get_type( $post_type_object->graphql_single_name );
							break;
						case $node instanceof Term && isset( $node->taxonomyName ):
							/** @var \WP_Taxonomy $tax_object */
							$tax_object = get_taxonomy( $node->taxonomyName );
							$type       = $type_registry->get_type( $tax_object->graphql_single_name );
							break;
						default:
							$type = null;
					}

					return $type;
				},
			]
		);
	}
}
