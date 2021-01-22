<?php

namespace WPGraphQL\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;

/**
 * Class Commenter
 *
 * @package WPGraphQL\Connection
 */
class Commenter {

	/**
	 * Register connections to Commenter type
	 *
	 * @return void
	 */
	public static function register_connections() {

		register_graphql_connection([
			'fromType'      => 'Comment',
			'toType'        => 'Commenter',
			'description'   => __( 'The author of the comment', 'wp-graphql' ),
			'fromFieldName' => 'author',
			'oneToOne'      => true,
			'resolve'       => function( $comment, $args, AppContext $context, ResolveInfo $info ) {

				/**
				 * If the comment has a user associated, use it to populate the author, otherwise return
				 * the $comment and the Union will use that to hydrate the CommentAuthor Type
				 */
				if ( ! empty( $comment->userId ) ) {
					$node = $context->get_loader( 'user' )->load( absint( $comment->userId ) );
				} else {
					$node = ! empty( $comment->commentId ) ? $context->get_loader( 'comment_author' )->load( $comment->commentId ) : null;
				}

				return [
					'node'   => $node,
					'source' => $comment,
				];

			},
		]);

	}
}
