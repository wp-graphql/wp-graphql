<?php
namespace WPGraphQL\Type\InterfaceType;

use Exception;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\Connection\PostObjectConnectionResolver;
use WPGraphQL\Model\Post;
use WPGraphQL\Registry\TypeRegistry;

class NodeWithFeaturedImage {

	/**
	 * Registers the NodeWithFeaturedImage Type to the Schema
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ) {

		register_graphql_interface_type(
			'NodeWithFeaturedImage',
			[
				'description' => __( 'A node that can have a featured image set', 'wp-graphql' ),
				'interfaces'  => [ 'Node' ],
				'connections' => [
					'featuredImage' => [
						'toType'   => 'MediaItem',
						'oneToOne' => true,
						'resolve'  => function ( Post $post, $args, AppContext $context, ResolveInfo $info ) {

							if ( empty( $post->featuredImageDatabaseId ) ) {
								return null;
							}

							$resolver = new PostObjectConnectionResolver( $post, $args, $context, $info, 'attachment' );
							$resolver->set_query_arg( 'p', absint( $post->featuredImageDatabaseId ) );

							return $resolver->one_to_one()->get_connection();

						},
					],
				],
				'fields'      => [
					'featuredImageId'         => [
						'type'        => 'ID',
						'description' => __( 'Globally unique ID of the featured image assigned to the node', 'wp-graphql' ),
					],
					'featuredImageDatabaseId' => [
						'type'        => 'Int',
						'description' => __( 'The database identifier for the featured image node assigned to the content node', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
