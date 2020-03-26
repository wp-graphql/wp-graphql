<?php
namespace WPGraphQL\Type\InterfaceType;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\Post;
use WPGraphQL\Registry\TypeRegistry;

class NodeWithRevisions {
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'NodeWithRevisions',
			[
				'description' => __( 'A node that can have revisions', 'wp-graphql' ),
				'fields'      => [
					'isRevision' => [
						'type'        => 'Boolean',
						'description' => __( 'True if the node is a revision of another node', 'wp-graphql' ),
						'resolve'     => function( Post $post, $args, $context, $info ) {
							return 'revision' === $post->post_type ? true : false;
						},
					],
					'revisionOf' => [
						'type'        => 'PostObjectUnion',
						'description' => __( 'If the current node is a revision, this field exposes the node this is a revision of. Returns null if the node is not a revision of another node.', 'wp-graphql' ),
						'resolve'     => function( Post $post, $args, AppContext $context, ResolveInfo $info ) {
							// @codingStandardsIgnoreLine.
							if ( 'revision' !== $post->post_type || ! isset( $post->parentId ) || ! absint( $post->parentId ) ) {
								return null;
							}

							// @codingStandardsIgnoreLine.
							return DataSource::resolve_post_object( $post->parentId, $context );
						},
					],
				],
			]
		);
	}
}
