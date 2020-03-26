<?php
namespace WPGraphQL\Data\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Model\Post;
use WPGraphQL\Model\PostType;

class ContentTypeConnectionResolver {

	/**
	 * Creates the connection for post types (content types)
	 *
	 * @param mixed       $source  The query results
	 * @param array       $args    The query arguments
	 * @param AppContext  $context The AppContext object
	 * @param ResolveInfo $info    The ResolveInfo object
	 *
	 * @since  0.8.0
	 * @return array
	 * @throws \Exception
	 */
	public static function resolve( $source, array $args, AppContext $context, ResolveInfo $info ) {

		$query_args = [];

		if ( $source instanceof Post ) {
			$query_args['name'] = $source->post_type;
		}
		$query_args['show_in_graphql'] = true;

		$post_types = get_post_types( $query_args );

		$post_types_array = [];
		foreach ( $post_types as $post_type ) {

			$post_type_object = get_post_type_object( $post_type );
			$model            = ! empty( $post_type_object ) ? new PostType( $post_type_object ) : null;

			if ( 'private' !== $model->get_visibility() ) {
				$post_types_array[] = $model;
			}
		}
		$connection = Relay::connectionFromArray( $post_types_array, $args );

		$nodes = [];
		if ( ! empty( $connection['edges'] ) && is_array( $connection['edges'] ) ) {
			foreach ( $connection['edges'] as $edge ) {
				$nodes[] = ! empty( $edge['node'] ) ? $edge['node'] : null;
			}
		}
		$connection['nodes'] = ! empty( $nodes ) ? $nodes : null;

		return ! empty( $post_types_array ) ? $connection : null;

	}

}
