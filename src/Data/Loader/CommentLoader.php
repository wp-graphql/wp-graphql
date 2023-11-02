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
	 * {@inheritDoc}
	 *
	 * @return ?\WPGraphQL\Model\Comment
	 * @throws \Exception
	 */
	protected function get_model( $entry, $key ) {
		if ( ! $entry instanceof \WP_Comment ) {
			return null;
		}

		$comment_model = new Comment( $entry );
		if ( empty( $comment_model->fields ) ) {
			return null;
		}

		return $comment_model;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int[] $keys Array of IDs to load
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
