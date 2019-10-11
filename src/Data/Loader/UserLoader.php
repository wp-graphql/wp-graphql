<?php
namespace WPGraphQL\Data\Loader;

use WPGraphQL\Model\User;

/**
 * Class UserLoader
 *
 * @package WPGraphQL\Data\Loader
 */
class UserLoader extends AbstractDataLoader {
	
	/**
	 * This stores an array of published author ID's
	 * 
	 * @access protected
	 */
	protected $published_authors = [];

	/**
	 * Given array of keys, loads and returns a map consisting of keys from `keys` array and loaded
	 * values
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

		$all_users = [];

		/**
		 * Prepare the args for the query. We're provided a specific
		 * set of IDs, so we want to query as efficiently as possible with
		 * as little overhead as possible. We don't want to return post counts,
		 * we don't want to include sticky posts, and we want to limit the query
		 * to the count of the keys provided. The query must also return results
		 * in the same order the keys were provided in.
		 */
		$args = [
			'include'     => $keys,
			'number'      => count( $keys ),
			'orderby'     => 'include',
			'count_total' => false,
			'fields'      => 'all_with_meta',
		];

		/**
		 * Query for the users and get the results
		 */
		$query = new \WP_User_Query( $args );
		$users = $query->get_results();

		/**
		 * If no users are returned, return an empty array
		 */
		if ( empty( $users ) || ! is_array( $users ) ) {
			return [];
		}
		
		$this->load_published_author_ids( $keys );
		
		foreach ( $keys as $id ) {
			$user = get_user_by( 'id', $id );

			$user_has_published_posts = in_array(
				absint( $id ),
				$this->published_authors,
				true 
			);

			$user->has_published_posts = $user_has_published_posts;

			$all_users[ $user->ID ] = new User( $user );
		}

		return $all_users;

	}

	/**
	 * This method accepts an array of user ID's, and stores an array of 
	 * user ID's for the subset that are published authors.
	 * 
	 * @param array $keys An array of post ID's
	 */
	protected function load_published_author_ids( $ids ) {
		$post_types = get_post_types( [ 'show_in_graphql' => true ] );

        unset( $post_types[ 'attachment' ] );
        unset( $post_types[ 'revision' ] );
		
		$stringified_ids        = implode( ',', $ids );
		$stringified_post_types = '"' . implode( '", "', $post_types ) . '"';

		$where = get_posts_by_author_sql( $post_types, true, null, false );
		
		global $wpdb;
        $results = $wpdb->get_results( 
			"SELECT
				ID
			FROM
				wp_users
			WHERE
				ID IN (
					SELECT
						DISTINCT post_author
					FROM
						wp_posts
					WHERE
						post_author IN ($stringified_ids)
						AND post_type IN ($stringified_post_types)
						AND post_status = 'publish'
				)",
			'ARRAY_A'
		);

		// flatten our ID's into a single level array
		$results_flat = array_map( 
			function( $item ) {
				return absint( $item['ID'] ) ?? null;
			}, 
			$results
		);

		$this->published_authors = array_unique(
			array_merge(
				$this->published_authors,
				$results_flat
			)
		);
	}

}
