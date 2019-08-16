<?php

namespace WPGraphQL\Data\Loader;

use GraphQL\Deferred;
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

		$loaded = [];

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

		/**
		 * Loop pver the keys and return an array of loaded_terms, where the key is the IDand the value
		 * is the comment object, passed through the Model layer
		 */
		foreach ( $keys as $key ) {

			/**
			 * Get the comment from the cache
			 */
			$comment_object = \WP_Comment::get_instance( $key );

			/**
			 * Return the instance through the Model Layer to ensure we only return
			 * values the consumer has access to.
			 */
			$loaded[ $key ] = new Deferred(
				function() use ( $comment_object ) {

					if ( ! $comment_object instanceof \WP_Comment ) {
						  return null;
					}

						return new Comment( $comment_object );
				}
			);
		}

		return ! empty( $loaded ) ? $loaded : [];

	}

}
