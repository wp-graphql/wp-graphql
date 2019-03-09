<?php

namespace WPGraphQL\Data\Loader;

use WPGraphQL\Model\Post;

/**
 * Class PostObjectLoader
 *
 * @package WPGraphQL\Data\Loader
 */
class PostObjectLoader extends AbstractDataLoader {

	/**
	 * Given array of keys, loads and returns a map consisting of keys from `keys` array and loaded
	 * posts as the values
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
		if ( empty( $keys ) ) {
			return null;
		}

		/**
		 * Configure the args for the query.
		 */
		$args      = [
			'post_type'           => 'any',
			'post_status'         => 'any',
			'posts_per_page'      => count( $keys ),
			'post__in'            => $keys,
			'orderby'             => 'post__in',
			'no_found_rows'       => true,
			'split_the_query'     => false,
			'ignore_sticky_posts' => true,
		];

		/**
		 * Ensure that WP_Query doesn't first ask for IDs since we already have them.
		 */
		add_filter( 'split_the_query', function ( $split, \WP_Query $query ) {
			if ( false === $query->get( 'split_the_query' ) ) {
				return false;
			}
			return $split;
		}, 10, 2 );

		/**
		 * Query the objects
		 */
		$query = new \WP_Query( $args );

		/**
		 * If there are no posts, return null
		 */
		if ( empty( $query->posts ) || ! is_array( $query->posts ) ) {
			return null;
		}

		/**
		 * Return an array of Post objects. The null
		 */
		return ! empty( $query->posts ) ? array_map( function ( $post_object ) {
			return new Post( $post_object );
		}, $query->posts ) : null;

	}

}
