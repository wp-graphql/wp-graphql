<?php

namespace WPGraphQL\Data\Loader;

use GraphQL\Deferred;
use GraphQL\Error\UserError;
use WPGraphQL\Model\Menu;
use WPGraphQL\Model\MenuItem;
use WPGraphQL\Model\Term;

/**
 * Class TermObjectLoader
 *
 * @package WPGraphQL\Data\Loader
 */
class TermObjectLoader extends AbstractDataLoader {

	/**
	 * @var array
	 */
	public $loaded_terms;

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

		$terms_by_id = [];
		foreach ( $terms as $term ) {
			$terms_by_id[ $term->term_id ] = $term;
		}

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
			$term_object = $terms_by_id[ $key ];

			if ( empty( $term_object ) || ! is_a( $term_object, 'WP_Term' ) ) {
				return null;
			}

			/**
			 * Return the instance through the Model to ensure we only
			 * return fields the consumer has access to.
			 */
			$this->loaded_terms[ $key ] = new Deferred(
				function () use ( $term_object ) {

					if ( ! $term_object instanceof \WP_Term ) {
						  return null;
					}

						/**
						 * For nav_menu_item terms, we want to pass through a different model
						 */
					if ( 'nav_menu' === $term_object->taxonomy ) {
						return new Menu( $term_object );
					}

						return new Term( $term_object );
				}
			);
		}

		return ! empty( $this->loaded_terms ) ? $this->loaded_terms : [];

	}

}
