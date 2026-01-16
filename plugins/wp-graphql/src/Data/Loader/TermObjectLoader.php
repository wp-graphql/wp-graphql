<?php

namespace WPGraphQL\Data\Loader;

use WPGraphQL\Model\Menu;
use WPGraphQL\Model\Term;

/**
 * Class TermObjectLoader
 *
 * @package WPGraphQL\Data\Loader
 */
class TermObjectLoader extends AbstractDataLoader {

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed|\WP_Term $entry The Term Object
	 *
	 * @return \WPGraphQL\Model\Term|\WPGraphQL\Model\Menu|null
	 * @throws \Exception
	 */
	protected function get_model( $entry, $key ) {
		if ( is_a( $entry, 'WP_Term' ) ) {

			/**
			 * For nav_menu terms, we want to pass through a different model
			 */
			if ( 'nav_menu' === $entry->taxonomy ) {
				$menu = new Menu( $entry );
				if ( empty( $menu->fields ) ) {
					return null;
				} else {
					return $menu;
				}
			} else {
				$term = new Term( $entry );
				if ( empty( $term->fields ) ) {
					return null;
				} else {
					return $term;
				}
			}
		}
		return null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int[] $keys
	 *
	 * @return array<int,\WP_Term|\WP_Error|null>
	 */
	public function loadKeys( array $keys ) {
		if ( empty( $keys ) ) {
			return $keys;
		}

		/**
		 * Prepare the args for the query. We're provided a specific set of IDs for terms,
		 * so we want to query as efficiently as possible with as little overhead as possible.
		 */
		$args = [
			'include'    => $keys,
			'number'     => count( $keys ),
			'orderby'    => 'include',
			'hide_empty' => false,
		];

		/**
		 * Execute the query. This adds the terms to the cache
		 */
		$query = new \WP_Term_Query( $args );
		$terms = $query->get_terms();

		if ( empty( $terms ) || ! is_array( $terms ) ) {
			return [];
		}

		$loaded = [];

		/**
		 * Loop over the keys and return an array of loaded_terms, where the key is the ID and the value is
		 * the Term passed through the Model layer
		 */
		foreach ( $keys as $key ) {

			/**
			 * The query above has added our objects to the cache, so now we can pluck
			 * them from the cache to pass through the model layer, or return null if the
			 * object isn't in the cache, meaning it didn't come back when queried.
			 */
			$loaded[ $key ] = get_term( (int) $key );
		}

		return $loaded;
	}
}
