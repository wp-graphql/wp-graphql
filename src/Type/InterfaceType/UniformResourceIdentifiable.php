<?php

namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Model\Comment;
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
				'description' => static function () {
					return __( 'An interface for content that can be accessed via a unique URI/URL path. Implemented by content types that have their own permalinks.', 'wp-graphql' );
				},
				'fields'      => static function () {
					return [
						'uri'           => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The unique resource identifier path', 'wp-graphql' );
							},
						],
						'id'            => [
							'type'        => [ 'non_null' => 'ID' ],
							'description' => static function () {
								return __( 'The globally unique ID for the object', 'wp-graphql' );
							},
						],
						'isContentNode' => [
							'type'        => [ 'non_null' => 'Boolean' ],
							'description' => static function () {
								return __( 'Whether the node is a Content Node', 'wp-graphql' );
							},
							'resolve'     => static function ( $node ) {
								return $node instanceof Post;
							},
						],
						'isTermNode'    => [
							'type'        => [ 'non_null' => 'Boolean' ],
							'description' => static function () {
								return __( 'Whether the node is a Term', 'wp-graphql' );
							},
							'resolve'     => static function ( $node ) {
								return $node instanceof Term;
							},
						],
						'isFrontPage'   => [
							'type'        => [ 'non_null' => 'Bool' ],
							'description' => static function () {
								return __( 'Whether the node represents the front page.', 'wp-graphql' );
							},
							'resolve'     => static function ( $node, $args, $context, $info ) {
								return isset( $node->isFrontPage ) && (bool) $node->isFrontPage;
							},
						],
						'isPostsPage'   => [
							'type'        => [ 'non_null' => 'Bool' ],
							'description' => static function () {
								return __( 'Whether  the node represents the blog page.', 'wp-graphql' );
							},
							'resolve'     => static function ( $node, $args, $context, $info ) {
								return isset( $node->isPostsPage ) && (bool) $node->isPostsPage;
							},
						],
						'isComment'     => [
							'type'        => [ 'non_null' => 'Boolean' ],
							'description' => static function () {
								return __( 'Whether the node is a Comment', 'wp-graphql' );
							},
							'resolve'     => static function ( $node ) {
								return $node instanceof Comment;
							},
						],
					];
				},
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
						case $node instanceof User:
							$type = $type_registry->get_type( 'User' );
							break;
						case $node instanceof PostType:
							$type = $type_registry->get_type( 'ContentType' );
							break;
						case $node instanceof Comment:
							$type = $type_registry->get_type( 'Comment' );
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
