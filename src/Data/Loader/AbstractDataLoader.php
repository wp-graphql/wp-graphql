<?php

namespace WPGraphQL\Data\Loader;

use Exception;
use Generator;
use GraphQL\Deferred;
use GraphQL\Utils\Utils;
use WPGraphQL\AppContext;
use WPGraphQL\Model\Model;

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
	 * Given a Database ID, the particular loader will buffer it and resolve it deferred.
	 *
	 * @param mixed|int|string $database_id The database ID for a particular loader to load an
	 *                                      object
	 *
	 * @return Deferred|null
	 * @throws Exception
	 */
	public function load_deferred( $database_id ) {

		if ( empty( $database_id ) ) {
			return null;
		}

		$database_id = absint( $database_id ) ? absint( $database_id ) : sanitize_text_field( $database_id );

		$this->buffer( [ $database_id ] );

		return new Deferred(
			function () use ( $database_id ) {
				return $this->load( $database_id );
			}
		);

	}

	/**
	 * Add keys to buffer to be loaded in single batch later.
	 *
	 * @param array $keys The keys of the objects to buffer
	 *
	 * @return $this
	 * @throws Exception
	 */
	public function buffer( array $keys ) {
		foreach ( $keys as $index => $key ) {
			$key = $this->key_to_scalar( $key );
			if ( ! is_scalar( $key ) ) {
				throw new Exception(
					get_class( $this ) . '::buffer expects all keys to be scalars, but key ' .
					'at position ' . $index . ' is ' . Utils::printSafe( $keys ) . '. ' .
					$this->get_scalar_key_hint( $key )
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
	 * @throws Exception
	 */
	public function load( $key ) {

		$key = $this->key_to_scalar( $key );
		if ( ! is_scalar( $key ) ) {
			throw new Exception(
				get_class( $this ) . '::load expects key to be scalar, but got ' . Utils::printSafe( $key ) .
				$this->get_scalar_key_hint( $key )
			);
		}
		if ( ! $this->shouldCache ) {
			$this->buffer = [];
		}
		$keys = [ $key ];
		$this->buffer( $keys );
		$result = $this->load_buffered();

		return isset( $result[ $key ] ) ? $this->normalize_entry( $result[ $key ], $key ) : null;
	}

	/**
	 * Adds the provided key and value to the cache. If the key already exists, no
	 * change is made. Returns itself for method chaining.
	 *
	 * @param mixed $key
	 * @param mixed $value
	 *
	 * @return $this
	 * @throws Exception
	 */
	public function prime( $key, $value ) {
		$key = $this->key_to_scalar( $key );
		if ( ! is_scalar( $key ) ) {
			throw new Exception(
				get_class( $this ) . '::prime is expecting scalar $key, but got ' . Utils::printSafe( $key )
				. $this->get_scalar_key_hint( $key )
			);
		}
		if ( null === $value ) {
			throw new Exception(
				get_class( $this ) . '::prime is expecting non-null $value, but got null. Double-check for null or ' .
				' use `clear` if you want to clear the cache'
			);
		}
		if ( ! $this->get_cached( $key ) ) {
			/**
			 * For adding third-party caching support.
			 * Use this filter to store the queried value in a cache.
			 *
			 * @param mixed  $value         Queried object.
			 * @param mixed  $key           Object key.
			 * @param string $loader_class  Loader classname. Use as a means of identified the loader.
			 * @param mixed  $loader        Loader instance.
			 */
			$this->set_cached( $key, $value );
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
			$key = $this->key_to_scalar( $key );
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
	 *
	 * @return AbstractDataLoader
	 * @deprecated in favor of clear_all
	 */
	public function clearAll() {
		return $this->clear_all();
	}

	/**
	 * Clears the entire cache. To be used when some event results in unknown
	 * invalidations across this particular `DataLoader`. Returns itself for
	 * method chaining.
	 *
	 * @return AbstractDataLoader
	 */
	public function clear_all() {
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
	 * @return array|Generator
	 * @throws Exception
	 *
	 * @deprecated Use load_many instead
	 */
	public function loadMany( array $keys, $asArray = false ) {
		return $this->load_many( $keys, $asArray );
	}

	/**
	 * Loads multiple keys. Returns generator where each entry directly corresponds to entry in
	 * $keys. If second argument $asArray is set to true, returns array instead of generator
	 *
	 * @param array $keys
	 * @param bool  $asArray
	 *
	 * @return array|Generator
	 * @throws Exception
	 */
	public function load_many( array $keys, $asArray = false ) {
		if ( empty( $keys ) ) {
			return [];
		}
		if ( ! $this->shouldCache ) {
			$this->buffer = [];
		}
		$this->buffer( $keys );
		$generator = $this->generate_many( $keys, $this->load_buffered() );

		return $asArray ? iterator_to_array( $generator ) : $generator;
	}

	/**
	 * Given an array of keys, this yields the object from the cached results
	 *
	 * @param array $keys   The keys to generate results for
	 * @param array $result The results for all keys
	 *
	 * @return Generator
	 */
	private function generate_many( array $keys, array $result ) {
		foreach ( $keys as $key ) {
			$key = $this->key_to_scalar( $key );
			yield isset( $result[ $key ] ) ? $this->get_model( $result[ $key ], $key ) : null;
		}
	}

	/**
	 * This checks to see if any items are in the buffer, and if there are this
	 * executes the loaders `loadKeys` method to load the items and adds them
	 * to the cache if necessary
	 *
	 * @return array
	 * @throws Exception
	 */
	private function load_buffered() {
		// Do not load previously-cached entries:
		$keysToLoad = [];
		foreach ( $this->buffer as $key => $unused ) {
			if ( ! $this->get_cached( $key ) ) {
				$keysToLoad[] = $key;
			}
		}

		$result = [];
		if ( ! empty( $keysToLoad ) ) {
			try {
				$loaded = $this->loadKeys( $keysToLoad );
			} catch ( Exception $e ) {
				throw new Exception(
					'Method ' . get_class( $this ) . '::loadKeys is expected to return array, but it threw: ' .
					$e->getMessage(),
					0,
					$e
				);
			}

			if ( ! is_array( $loaded ) ) {
				throw new Exception(
					'Method ' . get_class( $this ) . '::loadKeys is expected to return an array with keys ' .
					'but got: ' . Utils::printSafe( $loaded )
				);
			}
			if ( $this->shouldCache ) {
				foreach ( $loaded as $key => $value ) {
					$this->set_cached( $key, $value );
				}
			}
		}

		// Re-include previously-cached entries to result:
		$result += array_intersect_key( $this->cached, $this->buffer );

		$this->buffer = [];

		return $result;
	}

	/**
	 * This helps to ensure null values aren't being loaded by accident.
	 *
	 * @param mixed $key
	 *
	 * @return string
	 */
	private function get_scalar_key_hint( $key ) {
		if ( null === $key ) {
			return ' Make sure to add additional checks for null values.';
		} else {
			return ' Try overriding ' . __CLASS__ . '::key_to_scalar if your keys are composite.';
		}
	}

	/**
	 * For loaders that need to decode keys, this method can help with that.
	 * For example, if we wanted to accept a list of RELAY style global IDs and pass them
	 * to the loader, we could have the loader centrally decode the keys into their
	 * integer values in the PostObjectLoader by overriding this method.
	 *
	 * @param mixed $key
	 *
	 * @return mixed
	 */
	protected function key_to_scalar( $key ) {
		return $key;
	}

	/**
	 * @param mixed $key
	 *
	 * @return mixed
	 * @deprecated Use key_to_scalar instead
	 */
	protected function keyToScalar( $key ) {
		return $this->key_to_scalar( $key );
	}

	/**
	 * @param mixed $entry The entry loaded from the dataloader to be used to generate a Model
	 * @param mixed $key   The Key used to identify the loaded entry
	 *
	 * @return null|Model
	 */
	protected function normalize_entry( $entry, $key ) {

		/**
		 * This filter allows the model generated by the DataLoader to be filtered.
		 *
		 * Returning anything other than null here will bypass the default model generation
		 * for an object.
		 *
		 * One example would be WooCommerce Products returning a custom Model for posts of post_type "product".
		 *
		 * @param null               $model                The filtered model to return. Default null
		 * @param mixed              $entry                The entry loaded from the dataloader to be used to generate a Model
		 * @param mixed              $key                  The Key used to identify the loaded entry
		 * @param AbstractDataLoader $abstract_data_loader The AbstractDataLoader instance
		 */
		$model         = null;
		$pre_get_model = apply_filters( 'graphql_dataloader_pre_get_model', $model, $entry, $key, $this );

		/**
		 * If a Model has been pre-loaded via filter, return it and skip the
		 */
		if ( ! empty( $pre_get_model ) ) {
			$model = $pre_get_model;
		} else {
			$model = $this->get_model( $entry, $key );
		}

		if ( $model instanceof Model && 'private' === $model->get_visibility() ) {
			return null;
		}

		/**
		 * Filter the model before returning.
		 *
		 * @param mixed              $model  The Model to be returned by the loader
		 * @param mixed              $entry  The entry loaded by dataloader that was used to create the Model
		 * @param mixed              $key    The Key that was used to load the entry
		 * @param AbstractDataLoader $loader The AbstractDataLoader Instance
		 */
		return apply_filters( 'graphql_dataloader_get_model', $model, $entry, $key, $this );
	}

	/**
	 * Returns a cached data object by key.
	 *
	 * @param mixed $key  Key.
	 *
	 * @return mixed
	 */
	protected function get_cached( $key ) {
		$value = null;
		if ( isset( $this->cached[ $key ] ) ) {
			$value = $this->cached[ $key ];
		}

		/**
		 * Use this filter to retrieving cached data objects from third-party caching system.
		 *
		 * @param mixed  $value         Value to be cached.
		 * @param mixed  $key           Key identifying object.
		 * @param string $loader_class  Loader class name.
		 * @param mixed  $loader        Loader instance.
		 */
		$value = apply_filters(
			'graphql_dataloader_get_cached',
			$value,
			$key,
			get_class( $this ),
			$this
		);

		if ( $value && ! isset( $this->cached[ $key ] ) ) {
			$this->cached[ $key ] = $value;
		}

		return $value;
	}

	/**
	 * Caches a data object by key.
	 *
	 * @param mixed $key    Key.
	 * @param mixed $value  Data object.
	 *
	 * @return mixed
	 */
	protected function set_cached( $key, $value ) {
		/**
		 * Use this filter to store entry in a third-party caching system.
		 *
		 * @param mixed  $value         Value to be cached.
		 * @param mixed  $key           Key identifying object.
		 * @param string $loader_class  Loader class name.
		 * @param mixed  $loader        Loader instance.
		 */
		$this->cached[ $key ] = apply_filters(
			'graphql_dataloader_set_cached',
			$value,
			$key,
			get_class( $this ),
			$this
		);
	}

	/**
	 * If the loader needs to do any tweaks between getting raw data from the DB and caching,
	 * this can be overridden by the specific loader and used for transformations, etc.
	 *
	 * @param mixed $entry The User Role object
	 * @param mixed $key   The Key to identify the user role by
	 *
	 * @return Model
	 */
	protected function get_model( $entry, $key ) {
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
