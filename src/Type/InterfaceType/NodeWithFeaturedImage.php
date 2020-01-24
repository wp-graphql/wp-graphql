<?php
namespace WPGraphQL\Type\InterfaceType;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\Post;
use WPGraphQL\Registry\TypeRegistry;

class NodeWithFeaturedImage {
	/**
	 * @param TypeRegistry $type_registry Instance of the Type Registry
	 */
	public static function register_type( $type_registry ) {

		register_graphql_interface_type(
			'NodeWithFeaturedImage',
			[
				'description' => __( 'A node that can have a featured image set', 'wp-graphql' ),
				'fields'      => [
					'featuredImage' => [
						'type'        => 'MediaItem',
						'description' => __( 'The featured image for the object', 'wp-graphql' ),
						'resolve'     => function( Post $post, $args, AppContext $context, ResolveInfo $info ) {
							// @codingStandardsIgnoreLine.
							if ( empty( $post->featuredImageId ) || ! absint( $post->featuredImageId ) ) {
								return null;
							}

							// @codingStandardsIgnoreLine.
							return DataSource::resolve_post_object( $post->featuredImageId, $context );
						},
					],
				],
			]
		);
	}
}
