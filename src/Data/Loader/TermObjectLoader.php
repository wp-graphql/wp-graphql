<?php

namespace WPGraphQL\Data\Loader;

use GraphQL\Deferred;
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

		$args = [
			'include' => $keys,
			'number'  => count( $keys ),
		];

		$query = new \WP_Term_Query( $args );
		$query->get_terms();

		foreach ( $keys as $key ) {
			$term_object                = get_term_by( 'id', $key );
			$this->loaded_terms[ $key ] = new Deferred( function () use ( $term_object ) {
				return new Term( $term_object );
			} );
		}

		return ! empty( $this->loaded_terms ) ? $this->loaded_terms : [];

	}

}
