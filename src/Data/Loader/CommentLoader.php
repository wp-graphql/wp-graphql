<?php

namespace WPGraphQL\Data\Loader;

use WPGraphQL\Model\Comment;

class CommentLoader extends AbstractDataLoader {

	/**
	 * @param array $keys
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function loadKeys( array $keys ) {

		if ( empty( $keys ) ) {
			return $keys;
		}

		$all_comments = [];
		$comments_by_id = [];
		$args = [
			'comment__in' => $keys,
			'orderby' => 'comment__in',
			'number' => count( $keys ),
			'no_found_rows' => true,
			'count' => false,
		];

		$query = new \WP_Comment_Query( $args );
		$comments = $query->get_comments();

		foreach ( $comments as $comment ) {
			$comments_by_id[ $comment->comment_ID ] = $comment;
		}

		foreach ( $keys as $key ) {

			$comment_object = ! empty( $comments_by_id[ $key ] ) ? $comments_by_id[ $key ] : null;

			if ( empty( $comment_object ) ) {
				throw new \Exception( sprintf( __( 'No post exists with id: %s', 'wp-graphql' ), $key ) );
			}

			$all_comments[ $key ] = new Comment( $comment_object );

		}

		return $all_comments;

	}

}
