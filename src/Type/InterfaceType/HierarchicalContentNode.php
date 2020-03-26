<?php
namespace WPGraphQL\Type\InterfaceType;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\Post;
use WPGraphQL\Registry\TypeRegistry;

class HierarchicalContentNode {
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'HierarchicalContentNode',
			[
				'description' => __( 'Content node with hierarchical (parent/child) relationships', 'wp-graphql' ),
				'fields'      => [
					'parent' => [
						'type'        => 'PostObjectUnion',
						'description' => __( 'The parent of the object. The parent object can be of various types', 'wp-graphql' ),
						'resolve'     => function( Post $post, $args, AppContext $context, ResolveInfo $info ) {
							// @codingStandardsIgnoreLine.
							if ( ! isset( $post->parentId ) || ! absint( $post->parentId ) ) {
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
