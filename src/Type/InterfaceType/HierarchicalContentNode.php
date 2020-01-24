<?php
namespace WPGraphQL\Type\InterfaceType;

use GraphQL\Deferred;
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
					'ancestors' => [
						'type'        => [
							'list_of' => 'PostObjectUnion',
						],
						'description' => esc_html__( 'Ancestors of the object', 'wp-graphql' ),
						'args'        => [
							'types' => [
								'type'        => [
									'list_of' => 'PostTypeEnum',
								],
								'description' => __( 'The types of ancestors to check for. Defaults to the same type as the current object', 'wp-graphql' ),
							],
						],
						'resolve'     => function( $source, $args, AppContext $context, ResolveInfo $info ) {
							$ancestor_ids = get_ancestors( $source->ID, $source->post_type );
							if ( empty( $ancestor_ids ) || ! is_array( $ancestor_ids ) ) {
								return null;
							}
							$context->getLoader( 'post_object' )->buffer( $ancestor_ids );

							return new Deferred(
								function() use ( $context, $ancestor_ids ) {
									// @codingStandardsIgnoreLine.
									return $context->getLoader( 'post_object' )->loadMany( $ancestor_ids );
								}
							);
						},
					],
					'parent'    => [
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
