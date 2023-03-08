<?php

namespace WPGraphQL\Type\InterfaceType;

use Exception;
use WPGraphQL\Model\Post;
use WPGraphQL\Model\Term;
use WPGraphQL\Registry\TypeRegistry;
use WPGraphQL\Type\ObjectType\User;

class MenuItemLinkable {

	/**
	 * Registers the MenuItemLinkable Interface Type
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry Instance of the WPGraphQL Type Registry
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {

		register_graphql_interface_type( 'MenuItemLinkable', [
			'description' => __( 'Nodes that can be linked to as Menu Items', 'wp-graphql' ),
			'interfaces'  => [ 'Node', 'UniformResourceIdentifiable', 'DatabaseIdentifier' ],
			'fields'      => [],
			'resolveType' => function ( $node ) use ( $type_registry ) {

				switch ( true ) {
					case $node instanceof Post:
						/** @var \WP_Post_Type $post_type_object */
						$post_type_object = get_post_type_object( $node->post_type );
						$type             = $type_registry->get_type( $post_type_object->graphql_single_name );
						break;
					case $node instanceof Term:
						/** @var \WP_Taxonomy $tax_object */
						$tax_object = get_taxonomy( $node->taxonomyName );
						$type       = $type_registry->get_type( $tax_object->graphql_single_name );
						break;
					default:
						$type = null;
				}

				return $type;

			},
		] );

	}
}
