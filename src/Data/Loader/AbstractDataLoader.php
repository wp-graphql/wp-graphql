<?php

namespace WPGraphQL\Data\Loader;

use GraphQL\Utils\Utils;
use WPGraphQL\AppContext;

/**
 * Class AbstractDataLoader
 *
 * @package WPGraphQL\Data\Loader
 */
abstract class AbstractDataLoader {

	/**
	 * Whether the loader should cache results or not. In some cases the loader may be used to just
	 * get content but not bother with caching it.
	 *
	 * Default: true
	 *
	 * @var bool
	 */
	private $shouldCache = true;

	/**
	 * This stores an array of items that have already been loaded
	 *
	 * @var array
	 */
	private $cached = [];

	/**
	 * This stores an array of IDs that need to be loaded
	 *
	 * @var array
	 */
	private $buffer = [];

	/**
	 * This stores a reference to the AppContext for the loader to make use of
	 *
	 * @var AppContext
	 */
	protected $context;

	/**
	 * AbstractDataLoader constructor.
	 *
	 * @param AppContext $context
	 */
	public function __construct( AppContext $context ) {
		$this->context = $context;
	}

	/**
	 * Add keys to buffer to be loaded in single batch later.
	 *
	 * @param $keys
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function buffer( array $keys ) {
		foreach ( $keys as $index => $key ) {
			$key = $this->keyToScalar( $key );
			if ( ! is_scalar( $key ) ) {
				throw new \Exception(
					get_class( $this ) . '::buffer expects all keys to be scalars, but key ' .
					'at position ' . $index . ' is ' . Utils::printSafe( $keys ) . '. ' .
					$this->getScalarKeyHint( $key )
				);
			}
			$this->buffer[ $key ] = 1;
		}

		return $this;
	}

	/**
	 * Loads a key and returns value represented by this key.
	 * Internally this method will load all currently buffered items and cache them locally.
	 *
	 * @param mixed $key
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function load( $key ) {
		$key = $this->keyToScalar( $key );
		if ( ! is_scalar( $key ) ) {
			throw new \Exception(
				get_class( $this ) . '::load expects key to be scalar, but got ' . Utils::printSafe( $key ) .
				$this->getScalarKeyHint( $key )
			);
		}
		if ( ! $this->shouldCache ) {
			$this->buffer = [];
		}
		$keys = [ $key ];
		$this->buffer( $keys );
		$result = $this->loadBuffered();

		return isset( $result[ $key ] ) ? $this->normalizeEntry( $result[ $key ], $key ) : null;
	}

	/**
	 * Adds the provided key and value to the cache. If the key already exists, no
	 * change is made. Returns itself for method chaining.
	 *
	 * @param mixed $key
	 * @param mixed $value
	 *
	 * @throws \Exception
	 * @return $this
	 */
	public function prime( $key, $value ) {
		$key = $this->keyToScalar( $key );
		if ( ! is_scalar( $key ) ) {
			throw new \Exception(
				get_class( $this ) . '::prime is expecting scalar $key, but got ' . Utils::printSafe( $key )
				. $this->getScalarKeyHint( $key )
			);
		}
		if ( null === $value ) {
			throw new \Exception(
				get_class( $this ) . '::prime is expecting non-null $value, but got null. Double-check for null or ' .
				' use `clear` if you want to clear the cache'
			);
		}
		if ( ! isset( $this->cached[ $key ] ) ) {
			$this->cached[ $key ] = $value;
		}

		return $this;
	}

	/**
	 * Clears the value at `key` from the cache, if it exists. Returns itself for
	 * method chaining.
	 *
	 * @param array $keys
	 *
	 * @return $this
	 */
	public function clear( array $keys ) {
		foreach ( $keys as $key ) {
			$key = $this->keyToScalar( $key );
			if ( isset( $this->cached[ $key ] ) ) {
				unset( $this->cached[ $key ] );
			}
		}

		return $this;
	}

	/**
	 * Clears the entire cache. To be used when some event results in unknown
	 * invalidations across this particular `DataLoader`. Returns itself for
	 * method chaining.
	 */
	public function clearAll() {
		$this->cached = [];

		return $this;
	}

	/**
	 * Loads multiple keys. Returns generator where each entry directly corresponds to entry in
	 * $keys. If second argument $asArray is set to true, returns array instead of generator
	 *
	 * @param array $keys
	 * @param bool  $asArray
	 *
	 * @return array|\Generator
	 * @throws \Exception
	 */
	public function loadMany( array $keys, $asArray = false ) {
		if ( empty( $keys ) ) {
			return [];
		}
		if ( ! $this->shouldCache ) {
			$this->buffer = [];
		}
		$this->buffer( $keys );
		$generator = $this->generateMany( $keys, $this->loadBuffered() );

		return $asArray ? iterator_to_array( $generator ) : $generator;
	}

	/**
	 * Given an array of keys, this yields the object from the cached results
	 *
	 * @param $keys
	 * @param $result
	 *
	 * @return \Generator
	 */
	private function generateMany( $keys, $result ) {
		foreach ( $keys as $key ) {
			$key = $this->keyToScalar( $key );
			yield isset( $result[ $key ] ) ? $this->normalizeEntry( $result[ $key ], $key ) : null;
		}
	}

	/**
	 * This checks to see if any items are in the buffer, and if there are this
	 * executes the loaders `loadKeys` method to load the items and adds them
	 * to the cache if necessary
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function loadBuffered() {
		// Do not load previously-cached entries:
		$keysToLoad = array_keys( array_diff_key( $this->buffer, $this->cached ) );
		$result     = [];
		if ( ! empty( $keysToLoad ) ) {
			try {
				$loaded = $this->loadKeys( $keysToLoad );
			} catch ( \Exception $e ) {
				throw new \Exception(
					'Method ' . get_class( $this ) . '::loadKeys is expected to return array, but it threw: ' .
					$e->getMessage(),
					null,
					$e
				);
			}
			if ( ! is_array( $loaded ) ) {
				throw new \Exception(
					'Method ' . get_class( $this ) . '::loadKeys is expected to return an array with keys ' .
					'but got: ' . Utils::printSafe( $loaded )
				);
			}
			if ( $this->shouldCache ) {
				$this->cached += $loaded;
			}
		}
		// Re-include previously-cached entries to result:
		$result      += array_intersect_key( $this->cached, $this->buffer );
		$this->buffer = [];

		return $result;
	}

	/**
	 * This helps to ensure null values aren't being loaded by accident.
	 *
	 * @param $key
	 *
	 * @return string
	 */
	private function getScalarKeyHint( $key ) {
		if ( null === $key ) {
			return ' Make sure to add additional checks for null values.';
		} else {
			return ' Try overriding ' . __CLASS__ . '::keyToScalar if your keys are composite.';
		}
	}

	/**
	 * For loaders that need to decode keys, this method can help with that.
	 * For example, if we wanted to accept a list of RELAY style global IDs and pass them
	 * to the loader, we could have the loader centrally decode the keys into their
	 * integer values in the PostObjectLoader by overriding this method.
	 *
	 * @param $key
	 *
	 * @return mixed
	 */
	protected function keyToScalar( $key ) {
		return $key;
	}

	/**
	 * If the loader needs to do any tweaks between getting raw data from the DB and caching,
	 * this can be overridden by the specific loader and used for transformations, etc.
	 *
	 * @param $entry
	 * @param $key
	 *
	 * @return mixed
	 */
	protected function normalizeEntry( $entry, $key ) {
		return $entry;
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
	 */
	abstract protected function loadKeys( array $keys );
}
