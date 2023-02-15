<?php
namespace WPGraphQL\Data\Loader;

use Exception;
use WPGraphQL\Model\CommentAuthor;

/**
 * Class CommentAuthorLoader
 *
 * @package WPGraphQL\Data\Loader
 */
class CommentAuthorLoader extends AbstractDataLoader {

	/**
	 * @param mixed $entry The User Role object
	 * @param mixed $key The Key to identify the user role by
	 *
	 * @return mixed|\WPGraphQL\Model\CommentAuthor
	 * @throws \Exception
	 */
	protected function get_model( $entry, $key ) {

		if ( ! $entry instanceof \WP_Comment ) {
			return null;
		}

		return new CommentAuthor( $entry );
	}

	/**
	 * @param array $keys
	 *
	 * @return array
	 */
	public function loadKeys( array $keys ) {
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
