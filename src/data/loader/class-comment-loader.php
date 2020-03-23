<?php
/**
 * Loads the comment models
 *
 * @package WPGraphQL\Data\Loader
 */

namespace WPGraphQL\Data\Loader;

use GraphQL\Deferred;
use WPGraphQL\Model\Comment;

/**
 * Class Comment_Loader
 */
class Comment_Loader extends Abstract_Data_Loader {

	/**
	 * Given array of keys, loads and returns a map consisting of keys from `keys` array and loaded
	 * comments as the values
	 *
	 * Note that order of returned values must match exactly the order of keys.
	 * If some entry is not available for given key - it must include null for the missing key.
	 *
	 * For example:
	 * load_keys(['a', 'b', 'c']) -> ['a' => 'value1, 'b' => null, 'c' => 'value3']
	 *
	 * @param array $keys Comment IDs to be loaded.
	 *
	 * @return array
	 */
	public function load_keys( array $keys = [] ) {

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

		if ( empty( $keys ) ) {
			return $keys;
		}

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
			$comment = new Comment( $comment_object );
			if ( ! isset( $comment->fields ) || empty( $comment->fields ) ) {
				$loaded[ $key ] = null;
			} else {
				$loaded[ $key ] = $comment;
			}
		}

		return ! empty( $loaded ) ? $loaded : [];
	}
}
