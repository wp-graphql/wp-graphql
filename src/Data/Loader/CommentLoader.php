<?php

namespace WPGraphQL\Data\Loader;

use WPGraphQL\Model\Comment;

/**
 * Class CommentLoader
 *
 * @package WPGraphQL\Data\Loader
 */
class CommentLoader extends AbstractDataLoader {

	/**
	 * Given array of keys, loads and returns a map consisting of keys from `keys` array and loaded
	 * comments as the values
	 *
	 * Note that order of returned values must match exactly the order of keys.
	 * If some entry is not available for given key - it must include null for the missing key.
	 *
	 * For example:
	 * loadKeys(['a', 'b', 'c']) -> ['a' => 'value1, 'b' => null, 'c' => 'value3']
	 *
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

			$all_comments[ $key ] = ! empty( $comment_object ) ? new Comment( $comment_object ) : null;

		}

		return $all_comments;

	}

}
