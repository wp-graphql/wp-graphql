<?php

namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Model\Post;
use WPGraphQL\Model\Term;
use WPGraphQL\Registry\TypeRegistry;
use WPGraphQL\Type\Object\User;

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
					'type'        => [ 'non_null' => 'String' ],
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
