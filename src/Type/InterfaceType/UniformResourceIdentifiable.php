<?php

namespace WPGraphQL\Type\InterfaceType;

use WP_Post_Type;
use WP_Taxonomy;
use WPGraphQL\Model\Post;
use WPGraphQL\Model\PostType;
use WPGraphQL\Model\Term;
use WPGraphQL\Model\User;
use WPGraphQL\Registry\TypeRegistry;

class UniformResourceIdentifiable {

	/**
	 * Registers the UniformResourceIdentifiable Interface to the Schema.
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'UniformResourceIdentifiable',
			[
				'interfaces'  => [ 'Node' ],
				'description' => __( 'Any node that has a URI', 'wp-graphql' ),
				'fields'      => [
					'uri'           => [
						'type'        => 'String',
						'description' => __( 'The unique resource identifier path', 'wp-graphql' ),
					],
					'id'            => [
						'type'        => [ 'non_null' => 'ID' ],
						'description' => __( 'The unique resource identifier path', 'wp-graphql' ),
					],
					'isContentNode' => [
						'type'        => [ 'non_null' => 'Boolean' ],
						'description' => __( 'Whether the node is a Content Node', 'wp-graphql' ),
						'resolve'     => function ( $node ) {
							return $node instanceof Post;
						},
					],
					'isTermNode'    => [
						'type'        => [ 'non_null' => 'Boolean' ],
						'description' => __( 'Whether the node is a Term', 'wp-graphql' ),
						'resolve'     => function ( $node ) {
							return $node instanceof Term;
						},
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
							/** @var \WP_Taxonomy $tax_object */
							$tax_object = get_taxonomy( $node->taxonomyName );
							$type       = $type_registry->get_type( $tax_object->graphql_single_name );
							break;
						case $node instanceof User:
							$type = $type_registry->get_type( 'User' );
							break;
						case $node instanceof PostType:
							$type = $type_registry->get_type( 'ContentType' );
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
