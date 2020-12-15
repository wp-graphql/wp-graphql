<?php
namespace WPGraphQL\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\Connection\ContentTypeConnectionResolver;
use WPGraphQL\Model\Post;
use WPGraphQL\Model\Taxonomy;

class ContentTypes {

	public static function register_connections() {

		/**
		 * Registers a connection from the RootQuery to the PostType Type
		 */
		register_graphql_connection(
			[
				'fromType'      => 'RootQuery',
				'toType'        => 'ContentType',
				'fromFieldName' => 'contentTypes',
				'resolve'       => function( $source, $args, $context, $info ) {
					$resolver = new ContentTypeConnectionResolver( $source, $args, $context, $info );
					return $resolver->get_connection();
				},
			]
		);

		register_graphql_connection(
			[
				'fromType'      => 'ContentNode',
				'toType'        => 'ContentType',
				'fromFieldName' => 'contentType',
				'resolve'       => function( Post $source, $args, $context, $info ) {

					if ( $source->isRevision ) {
						$parent    = get_post( $source->parentDatabaseId );
						$post_type = isset( $parent->post_type ) ? $parent->post_type : null;
					} else {
						$post_type = isset( $source->post_type ) ? $source->post_type : null;
					}

					if ( empty( $post_type ) ) {
						return null;
					}

					$resolver = new ContentTypeConnectionResolver( $source, $args, $context, $info );
					return $resolver->one_to_one()->set_query_arg( 'name', $post_type )->get_connection();
				},
				'oneToOne'      => true,
			]
		);

		register_graphql_connection([
			'fromType'      => 'Taxonomy',
			'toType'        => 'ContentType',
			'description'   => __( 'List of Content Types associated with the Taxonomy', 'wp-graphql' ),
			'fromFieldName' => 'connectedContentTypes',
			'resolve'       => function( Taxonomy $taxonomy, $args, AppContext $context, ResolveInfo $info ) {

				$connected_post_types = ! empty( $taxonomy->object_type ) ? $taxonomy->object_type : [];
				$resolver             = new ContentTypeConnectionResolver( $taxonomy, $args, $context, $info );
				$resolver->set_query_arg( 'contentTypeNames', $connected_post_types );
				return $resolver->get_connection();

			},
		]);

	}

}
