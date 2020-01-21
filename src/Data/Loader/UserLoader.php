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
		$query->get_results();

		/**
		 * Loop over the Users and return an array of loaded_users,
		 * where the key is the ID and the value is the Post passed through
		 * the model layer.
		 */
		foreach ( $keys as $key ) {
			$user = get_user_by( 'id', $key );
			if ( $user instanceof \WP_User ) {
				$loaded_users[ $user->ID ] = new User( $user );
			} elseif ( ! isset( $all_users[0] ) ) {
				$loaded_users[0] = null;
			}
		}
		return ! empty( $loaded_users ) ? $loaded_users : [];

	}

}
