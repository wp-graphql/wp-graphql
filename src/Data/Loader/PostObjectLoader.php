<?php

namespace WPGraphQL\Data\Loader;

use WPGraphQL\Model\MenuItem;
use WPGraphQL\Model\Post;

/**
 * Class PostObjectLoader
 *
 * @package WPGraphQL\Data\Loader
 */
class PostObjectLoader extends AbstractDataLoader {

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed|\WP_Post $entry The Post Object
	 *
	 * @return \WPGraphQL\Model\Post|\WPGraphQL\Model\MenuItem|null
	 */
	protected function get_model( $entry, $key ) {
		if ( ! $entry instanceof \WP_Post ) {
			return null;
		}

		/**
		 * If there's a Post Author connected to the post, we need to resolve the
		 * user as it gets set in the globals via `setup_post_data()` and doing it this way
		 * will batch the loading so when `setup_post_data()` is called the user
		 * is already in the cache.
		 */
		$context = $this->context;

		if ( ! empty( $entry->post_author ) && absint( $entry->post_author ) ) {
			$user_id = $entry->post_author;
			$context->get_loader( 'user' )->load_deferred( $user_id );
		}

		if ( 'revision' === $entry->post_type && ! empty( $entry->post_parent ) && absint( $entry->post_parent ) ) {
			$post_parent = $entry->post_parent;
			$context->get_loader( 'post' )->load_deferred( $post_parent );
		}

		if ( 'nav_menu_item' === $entry->post_type ) {
			return new MenuItem( $entry );
		}

		$post = new Post( $entry );
		if ( empty( $post->fields ) ) {
			return null;
		}

		return $post;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string|int,\WP_Post|null>
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
		$post_types = \WPGraphQL::get_allowed_post_types();
		$post_types = array_merge( $post_types, [ 'revision', 'nav_menu_item' ] );
		$args       = [
			'post_type'           => $post_types,
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
		add_filter(
			'split_the_query',
			static function ( $split, \WP_Query $query ) {
				if ( false === $query->get( 'split_the_query' ) ) {
					return false;
				}

				return $split;
			},
			10,
			2
		);
		new \WP_Query( $args );
		$loaded_posts = [];
		foreach ( $keys as $key ) {
			/**
			 * The query above has added our objects to the cache
			 * so now we can pluck them from the cache to return here
			 * and if they don't exist we can throw an error, otherwise
			 * we can proceed to resolve the object via the Model layer.
			 */
			$post_object = get_post( (int) $key );

			if ( ! $post_object instanceof \WP_Post ) {
				$loaded_posts[ $key ] = null;
			} else {

				/**
				 * Once dependencies are loaded, return the Post Object
				 */
				$loaded_posts[ $key ] = $post_object;
			}
		}
		return $loaded_posts;
	}
}
