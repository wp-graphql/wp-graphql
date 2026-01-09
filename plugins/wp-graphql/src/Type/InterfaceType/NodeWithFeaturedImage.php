<?php
namespace WPGraphQL\Type\InterfaceType;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\Connection\PostObjectConnectionResolver;
use WPGraphQL\Model\Post;
use WPGraphQL\Registry\TypeRegistry;

class NodeWithFeaturedImage {

	/**
	 * Registers the NodeWithFeaturedImage Type to the Schema
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'NodeWithFeaturedImage',
			[
				'description' => static function () {
					return __( 'Content that can have a primary image attached. This image is typically used for thumbnails, social sharing, and prominent display in the presentation layer.', 'wp-graphql' );
				},
				'interfaces'  => [ 'Node' ],
				'connections' => [
					'featuredImage' => [
						'toType'   => 'MediaItem',
						'oneToOne' => true,
						'resolve'  => static function ( Post $post, $args, AppContext $context, ResolveInfo $info ) {
							if ( empty( $post->featuredImageDatabaseId ) ) {
								return null;
							}

							$resolver = new PostObjectConnectionResolver( $post, $args, $context, $info, 'attachment' );
							$resolver->set_query_arg( 'p', absint( $post->featuredImageDatabaseId ) );

							return $resolver->one_to_one()->get_connection();
						},
					],
				],
				'fields'      => static function () {
					return [
						'featuredImageId'         => [
							'type'        => 'ID',
							'description' => static function () {
								return __( 'Globally unique ID of the featured image assigned to the node', 'wp-graphql' );
							},
						],
						'featuredImageDatabaseId' => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'The database identifier for the featured image node assigned to the content node', 'wp-graphql' );
							},
						],
					];
				},
			]
		);
	}
}
