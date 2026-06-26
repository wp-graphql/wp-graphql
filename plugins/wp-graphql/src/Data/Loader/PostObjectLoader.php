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
		 * PROTOTYPE (abilities-under-the-hood): when enabled, source the fetch
		 * AND the per-row permission decision from the `wpgraphql/get-posts`
		 * ability instead of running WP_Query + leaving is_private() to the Model.
		 *
		 * The ability runs its own WP_Query( post__in ) internally (which warms
		 * WP's object cache exactly like the native path below) and returns only
		 * the posts the current user may view. We then pull the WP_Post objects
		 * from that warmed cache. The Model trusts the ability's filtering via the
		 * graphql_pre_model_data_is_private hook in resolve.php.
		 *
		 * This is the seam under test: does delegating to the ability cost more
		 * than it saves? (See counters surfaced in extensions.abilitiesPrototype.)
		 */
		if ( function_exists( 'wpgraphql_proto_resolve_mode' ) && in_array( wpgraphql_proto_resolve_mode(), [ 'permission', 'output' ], true ) && function_exists( 'wp_get_ability' ) ) {
			$ability = wp_get_ability( 'wpgraphql/get-posts' );
			if ( $ability ) {
				$ability_input = [
					'include'       => array_map( 'intval', $keys ),
					'include_total' => false,
				];

				/**
				 * Experiment B ('output' mode): the ability is the data source, so
				 * the loader must request a fixed payload. It cannot pass the
				 * GraphQL selection set (loaders batch by ID and never see it), so
				 * it asks for everything a downstream field might need — which makes
				 * the ability render the_content for every node up front, even when
				 * the query only selected `title`.
				 */
				if ( 'output' === wpgraphql_proto_resolve_mode() && function_exists( 'wpgraphql_proto_output_fields' ) ) {
					$ability_input['fields'] = wpgraphql_proto_output_fields();
				}

				$result      = $ability->execute( $ability_input );
				$visible_ids = [];
				if ( ! is_wp_error( $result ) && isset( $result['posts'] ) && is_array( $result['posts'] ) ) {
					foreach ( $result['posts'] as $row ) {
						if ( isset( $row['databaseId'] ) ) {
							$visible_ids[ (int) $row['databaseId'] ] = true;
						}
					}
				}

				$loaded_posts = [];
				foreach ( $keys as $key ) {
					$id = (int) $key;
					if ( ! isset( $visible_ids[ $id ] ) ) {
						// Dropped by the ability's per-row permission gate.
						$loaded_posts[ $key ] = null;
						continue;
					}
					$post_object          = get_post( $id ); // served from the cache the ability warmed.
					$loaded_posts[ $key ] = $post_object instanceof \WP_Post ? $post_object : null;
				}
				return $loaded_posts;
			}
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
