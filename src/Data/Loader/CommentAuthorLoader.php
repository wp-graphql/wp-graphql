<?php
namespace WPGraphQL\Data\Loader;
use WPGraphQL\Model\CommentAuthor;

/**
 * Class CommentAuthorLoader
 *
 * @package WPGraphQL\Data\Loader
 */
class CommentAuthorLoader extends AbstractDataLoader {

	/**
	 * @param array $keys
	 *
	 * @return array|CommentAuthor
	 * @throws \Exception
	 */
	public function loadKeys( array $keys ) {
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
		 * Loop over the keys and return an array of loaded_terms, where the key is the IDand the value
		 * is the comment object, passed through the Model layer
		 */
		foreach ( $keys as $key ) {

			/**
			 * Get the comment from the cache
			 */
			$comment_object = \WP_Comment::get_instance( $key );

			if ( ! isset( $comment_object->comment_author ) ) {
				return null;
			}

			/**
			 * Return the instance through the Model Layer to ensure we only return
			 * values the consumer has access to.
			 */
			$comment = new CommentAuthor( $comment_object );
			if ( ! isset( $comment->fields ) || empty( $comment->fields ) ) {
				$loaded[ $key ] = null;
			} else {
				$loaded[ $key ] = $comment;
			}
		}

		return ! empty( $loaded ) ? $loaded : [];

	}
}
