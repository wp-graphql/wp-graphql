<?php
namespace WPGraphQL\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\Connection\PostObjectConnectionResolver;
use WPGraphQL\Model\Post;

/**
 * Class MediaItems
 *
 * @package WPGraphQL\Connection
 */
class MediaItems {

	/**
	 * Register connections to MediaItems
	 *
	 * @return void
	 */
	public static function register_connections() {

		register_graphql_connection([
			'fromType'      => 'NodeWithFeaturedImage',
			'toType'        => 'MediaItem',
			'fromFieldName' => 'featuredImage',
			'oneToOne'      => true,
			'resolve'       => function( Post $post, $args, AppContext $context, ResolveInfo $info ) {

				if ( empty( $post->featuredImageDatabaseId ) ) {
					return null;
				}

				$resolver = new PostObjectConnectionResolver( $post, $args, $context, $info, 'attachment' );
				$resolver->set_query_arg( 'p', absint( $post->featuredImageDatabaseId ) );
				return $resolver->one_to_one()->get_connection();

			},
		]);

	}
}
