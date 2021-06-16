<?php

namespace WPGraphQL\Type\InterfaceType;

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
	 */
	public static function register_type( TypeRegistry $type_registry ) {

		register_graphql_interface_type( 'MenuItemLinkable', [
			'description' => __( 'Nodes that can be linked to as Menu Items', 'wp-graphql' ),
			'fields'      => [
				'uri'        => [
					'type'        => 'String',
					'description' => __( 'The unique resource identifier path', 'wp-graphql' ),
				],
				'id'         => [
					'type'        => [ 'non_null' => 'ID' ],
					'description' => __( 'The unique resource identifier path', 'wp-graphql' ),
				],
				'databaseId' => [
					'type'        => [
						'non_null' => 'Int',
					],
					'description' => __( 'The unique resource identifier path', 'wp-graphql' ),
				],
			],
			'resolveType' => function ( $node ) use ( $type_registry ) {

				switch ( true ) {
					case $node instanceof Post:
						/** @var \WP_Post_Type $post_type_object */
						$post_type_object = get_post_type_object( $node->post_type );
						$type             = $type_registry->get_type( $post_type_object->graphql_single_name );
						break;
					case $node instanceof Term:
						/** @var \WP_Taxonomy $taxonomy_object */
						$taxonomy_object = get_taxonomy( $node->taxonomyName );
						$type            = $type_registry->get_type( $taxonomy_object->graphql_single_name );
						break;
					default:
						$type = null;
				}

				return $type;

			},
		] );

	}
}
