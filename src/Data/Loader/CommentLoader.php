<?php

namespace WPGraphQL\Data\Loader;

use Exception;
use WPGraphQL\Model\Comment;

/**
 * Class CommentLoader
 *
 * @package WPGraphQL\Data\Loader
 */
class CommentLoader extends AbstractDataLoader {

	/**
	 * @param mixed $entry The User Role object
	 * @param mixed $key The Key to identify the user role by
	 *
	 * @return mixed|Comment|null
	 * @throws Exception
	 */
	protected function get_model( $entry, $key ) {

		if ( ! $entry instanceof \WP_Comment ) {
			return null;
		}

		$comment_model = new Comment( $entry );
		if ( ! isset( $comment_model->fields ) || empty( $comment_model->fields ) ) {
			return null;
		}

		return $comment_model;
	}

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
	 * @throws Exception
	 */
	public function loadKeys( array $keys = [] ) {

		/**
		 * Prepare the args for the query. We're provided a specific set of IDs of comments
		 * so we want to query as efficiently as possible with as little overhead to get the comment
		 * objects. No need to count the rows, etc.
		 */
		$args = [
			'comment__in'   => $keys,
			'orderby'       => 'comment__in',
			'number'        => count( $keys ),
			'no_found_rows' => true,
			'count'         => false,
		];

		/**
		 * Execute the query. Call get_comments() to add them to the cache.
		 */
		$query = new \WP_Comment_Query( $args );
		$query->get_comments();
		$loaded = [];
		foreach ( $keys as $key ) {
			$loaded[ $key ] = \WP_Comment::get_instance( $key );
		}
		return $loaded;
	}

}
