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
					'parent'           => [
						'type'        => 'PostObjectUnion',
						'description' => __( 'The parent of the object. The parent object can be of various types', 'wp-graphql' ),
						'resolve'     => function( Post $post, $args, AppContext $context, ResolveInfo $info ) {
							// @codingStandardsIgnoreLine.
							if ( ! isset( $post->parentDatabaseId ) || ! absint( $post->parentDatabaseId ) ) {
								return null;
							}

							// @codingStandardsIgnoreLine.
							return DataSource::resolve_post_object( $post->parentDatabaseId, $context );
						},
					],
					'parentId'         => [
						'type'        => 'ID',
						'description' => __( 'The globally unique identifier of the parent object.', 'wp-graphql' ),
					],
					'parentDatabaseId' => [
						'type'        => 'Int',
						'description' => __( 'Database id of the parent object', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
