<?php
namespace WPGraphQL\Data\Loader;

use Exception;
use WPGraphQL\Model\User;

/**
 * Class UserLoader
 *
 * @package WPGraphQL\Data\Loader
 */
class UserLoader extends AbstractDataLoader {

	/**
	 * @param mixed $entry The User Role object
	 * @param mixed $key The Key to identify the user role by
	 *
	 * @return mixed|User
	 * @throws Exception
	 */
	protected function get_model( $entry, $key ) {
		if ( $entry instanceof \WP_User ) {
			return new User( $entry );
		} else {
			return null;
		}
	}

	/**
	 * The data loader always returns a user object if it exists, but we need to
	 * separately determine whether the user should be considered private. The
	 * WordPress frontend does not expose authors without published posts, so our
	 * privacy model follows that same convention.
	 *
	 * Example return format for input "[ 1, 2 ]":
	 *
	 * [
	 *   2 => true,  // User 2 is public (has published posts)
	 * ]
	 *
	 * In this example, user 1 is not public (has no published posts) and is
	 * omitted from the returned array.
	 *
	 * @param array $keys Array of author IDs (int).
	 *
	 * @return array
	 */
	public function get_public_users( array $keys ) {

		// Get public post types that are set to show in GraphQL
		// as public users are determined by whether they've published
		// content in one of these post types
		$post_types = get_post_types( [
			'public'          => true,
			'show_in_graphql' => true,
		] );

		/**
		 * Exclude revisions and attachments, since neither ever receive the
		 * "publish" post status.
		 */
		unset( $post_types['revision'], $post_types['attachment'] );

		/**
		 * Only retrieve public posts by the provided author IDs. Also,
		 * get_posts_by_author_sql only accepts a single author ID, so we'll need to
		 * add our own IN statement.
		 */
		$author_id   = null;
		$public_only = true;

		// @phpstan-ignore-next-line
		$where = get_posts_by_author_sql( $post_types, true, $author_id, $public_only );
		$ids   = implode( ', ', array_fill( 0, count( $keys ), '%d' ) );
		$count = count( $keys );

		global $wpdb;

		$results = $wpdb->get_results(
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$wpdb->prepare(
				"SELECT DISTINCT `post_author` FROM $wpdb->posts $where AND `post_author` IN ( $ids ) LIMIT $count",
				$keys
			)
		);

		/**
		 * Empty results or error.
		 */
		if ( ! is_array( $results ) ) {
			return [];
		}

		/**
		 * Reduce to an associative array that can be easily consumed.
		 */
		return array_reduce(
			$results,
			static function ( $carry, $result ) {
				$carry[ (int) $result->post_author ] = true;
				return $carry;
			},
			[]
		);
	}

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
	 * @throws Exception
	 */
	public function loadKeys( array $keys ) {

		if ( empty( $keys ) ) {
			return $keys;
		}

		/**
		 * Prepare the args for the query. We're provided a specific
		 * set of IDs, so we want to query as efficiently as possible with
		 * as little overhead as possible. We don't want to return post counts,
		 * we don't want to include sticky posts, and we want to limit the query
		 * to the count of the keys provided. We don't care about the order since we
		 * will reorder them ourselves to match the order of the provided keys.
		 */
		$args = [
			'include'     => $keys,
			'number'      => count( $keys ),
			'count_total' => false,
			'fields'      => 'all_with_meta',
		];

		/**
		 * Query for the users and get the results
		 */
		$query = new \WP_User_Query( $args );
		$query->get_results();

		/**
		 * Determine which of the users are public (have published posts).
		 */
		$public_users = $this->get_public_users( $keys );

		/**
		 * Loop over the keys and reduce to an associative array, providing the
		 * WP_User instance (if found) or null. This ensures that the returned array
		 * has the same keys that were provided and in the same order.
		 */
		return array_reduce(
			$keys,
			function ( $carry, $key ) use ( $public_users ) {
				$user = get_user_by( 'id', $key ); // Cached via previous WP_User_Query.

				if ( $user instanceof \WP_User ) {
					/**
					 * Set a property on the user that can be accessed by the User model.
					 */
					// @phpstan-ignore-next-line
					$user->is_private = ! isset( $public_users[ $key ] );

					$carry[ $key ] = $user;
				} else {
					$carry[ $key ] = null;
				}

				return $carry;
			},
			[]
		);
	}

}
