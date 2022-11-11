<?php
namespace WPGraphQL\Data\Loader;

use Exception;
use WPGraphQL\Model\GuestCommenter;

/**
 * Class GuestCommenterLoader
 *
 * @package WPGraphQL\Data\Loader
 */
class GuestCommenterLoader extends AbstractDataLoader {

	/**
	 * @param mixed $entry The Comment object
	 * @param mixed $key The Key to identify the comment by
	 *
	 * @return mixed|GuestCommenter
	 * @throws Exception
	 */
	protected function get_model( $entry, $key ) {

		if ( ! $entry instanceof \WP_Comment && ! empty( $entry->user_id ) ) {
			return null;
		}

		return new GuestCommenter( $entry );
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
			'user_id'       => 0,
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
