<?php
namespace WPGraphQL\Data\Loader;

use WPGraphQL\Model\MenuItem;

/**
 * Class MenuItemLoader
 *
 * @package WPGraphQL\Data\Loader
 */
class MenuItemLoader extends AbstractDataLoader {

	/**
	 * Given array of keys, loads and returns a map consisting of keys from `keys` array and loaded
	 * menu items as the values
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

		/**
		 * If there are no keys, return null and don't execute the query.
		 */
		$all_posts = [];
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
			'post_type'           => 'nav_menu_item',
			'post_status'         => 'any',
			'posts_per_page'      => count( $keys ),
			'post__in'            => $keys,
			'orderby'             => 'post__in',
			'no_found_rows'       => true,
			'split_the_query'     => true,
			'ignore_sticky_posts' => true,
		];

		/**
		 * Ensure that WP_Query doesn't first ask for IDs since we already have them.
		 */
		add_filter(
			'split_the_query',
			function ( $split, \WP_Query $query ) {
				if ( false === $query->get( 'split_the_query' ) ) {
					return false;
				}
				return $split;
			},
			10,
			2
		);

		new \WP_Query( $args );

		/**
		 * Loop over the posts and return an array of all_posts,
		 * where the key is the ID and the value is the Post passed through
		 * the model layer.
		 */
		foreach ( $keys as $key ) {

			/**
			 * The query above has added our objects to the cache
			 * so now we can pluck them from the cache to return here
			 * and if they don't exist we can throw an error, otherwise
			 * we can proceed to resolve the object via the Model layer.
			 */
			$post_object = get_post( absint( $key ) );

			/**
			 * Return the instance through the Model to ensure we only
			 * return fields the consumer has access to.
			 */
			$all_posts[ $key ] = ! empty( $post_object ) ? new MenuItem( $post_object ) : null;
		}

		return $all_posts;

	}

}
