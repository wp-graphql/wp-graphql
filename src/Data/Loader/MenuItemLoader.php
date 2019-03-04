<?php
namespace WPGraphQL\Data\Loader;

use GraphQL\Deferred;
use WPGraphQL\Model\MenuItem;
use WPGraphQL\Model\Post;

class MenuItemLoader extends AbstractDataLoader {

	/**
	 * @param array $keys
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function loadKeys( array $keys ) {

		$all_posts = [];
		$args = [
			'post_type' => 'nav_menu_item',
			'post_status' => 'any',
			'posts_per_page' => count( $keys ),
			'post__in' => $keys,
			'orderby' => 'post__in',
			'no_found_rows' => true,
			'split_the_query' => true,
			'ignore_sticky_posts' => true,
		];

		/**
		 * Ensure that WP_Query doesn't first ask for IDs since we already have them.
		 */
		add_filter( 'split_the_query', function( $split, \WP_Query $query ) {
			if ( false === $query->get('split_the_query' ) ) {
				return false;
			}
			return $split;
		}, 10, 2 );

		$query = new \WP_Query( $args );

		if ( empty( $keys ) || empty( $query->posts ) || ! is_array( $query->posts ) ) {
			return null;
		}

		foreach ( $query->posts as $post_object ) {
			$all_posts[ $post_object->ID ] = new MenuItem( $post_object );
		}

		return array_filter( $all_posts );

	}

}
