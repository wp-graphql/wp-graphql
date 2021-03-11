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
	 * @param TypeRegistry $type_registry Instance of the WPGraphQL Type Registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ) {

		register_graphql_interface_type( 'MenuItemLinkable', [
			'interfaces'  => [ 'Node', 'UniformResourceIdentifiable', 'DatabaseIdentifier' ],
			'description' => __( 'Nodes that can be linked to as Menu Items', 'wp-graphql' ),
			'fields'      => [],
			'resolveType' => function( $node ) use ( $type_registry ) {

				switch ( true ) {
					case $node instanceof Post:
						$type = $type_registry->get_type( get_post_type_object( $node->post_type )->graphql_single_name );
						break;
					case $node instanceof Term:
						$type = $type_registry->get_type( get_taxonomy( $node->taxonomyName )->graphql_single_name );
						break;
					default:
						$type = null;
				}

				return $type;

			},
		] );

	}
}
