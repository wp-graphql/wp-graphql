<?php

namespace WPGraphQL\SmartCache;

use WPGraphQL\SmartCache\Cache\Query;

/**
 * The cache key is a hash of a fixed set of parts, so any request context that
 * changes the response but is absent from those parts is invisible to the cache.
 *
 * `graphql_cache_query_key_parts` lets a site add such a dimension -- a locale, a
 * currency, a feature flag -- so two requests that differ only by that context no
 * longer resolve to the same key and serve each other's stored response.
 *
 * The unfiltered key is pinned to a literal hash below. It must not change: a
 * different default would invalidate every entry already in the cache on upgrade.
 */
class QueryCacheKeyPartsFilterTest extends \Codeception\TestCase\WPTestCase {

	const FILTER = 'graphql_cache_query_key_parts';

	/**
	 * `query { __typename }` normalized by the printer and hashed with
	 * user/variables/operation all empty. Pinned so any change to the default
	 * parts -- reordering, renaming, or an added default part -- fails here.
	 */
	const DEFAULT_KEY = '6d6d79c0c0a0803c19de576e1caa778c258cc1c345d46fd6b8c8873f61ac9afd';

	/**
	 * The same document, hashed with variables and a named operation supplied.
	 */
	const DEFAULT_KEY_WITH_VARS = '12b1696d4a5b319649b71f25b90365a2f6c59253cc7b52ce697389ebd5406878';

	const QUERY = 'query { __typename }';

	public function tearDown(): void {
		remove_all_filters( self::FILTER );
		parent::tearDown();
	}

	/**
	 * build_key() is exercised directly. The class never assigns $request, so
	 * there is no request to construct here and the user part is always 0.
	 */
	private function build_key( $query = self::QUERY, $variables = null, $operation = null ) {
		$query_cache = new Query();

		return $query_cache->build_key( null, $query, $variables, $operation );
	}

	public function testDefaultKeyIsUnchanged() {
		$this->assertSame(
			self::DEFAULT_KEY,
			$this->build_key(),
			'The unfiltered cache key must not change. A different default invalidates every cached entry on upgrade.'
		);
	}

	public function testDefaultKeyWithVariablesIsUnchanged() {
		$this->assertSame(
			self::DEFAULT_KEY_WITH_VARS,
			$this->build_key( 'query GetPost($id: ID!) { post(id: $id) { title } }', [ 'id' => 42 ], 'GetPost' ),
			'The unfiltered cache key must not change when variables and an operation are supplied.'
		);
	}

	public function testDefaultKeyIsDeterministic() {
		$this->assertSame(
			$this->build_key(),
			$this->build_key(),
			'Repeated calls with identical input must produce an identical key, or nothing would ever be a cache hit.'
		);
	}

	public function testFilterChangesTheKey() {
		$unfiltered = $this->build_key();

		add_filter(
			self::FILTER,
			function ( $parts ) {
				$parts['locale'] = 'fr_FR';

				return $parts;
			}
		);

		$this->assertNotSame(
			$unfiltered,
			$this->build_key(),
			'Adding a part through the filter must change the key, otherwise responses differing only by that part still collide.'
		);
	}

	public function testDifferentFilterValuesProduceDifferentKeys() {
		add_filter(
			self::FILTER,
			function ( $parts ) {
				$parts['locale'] = 'fr_FR';

				return $parts;
			}
		);
		$french = $this->build_key();

		remove_all_filters( self::FILTER );

		add_filter(
			self::FILTER,
			function ( $parts ) {
				$parts['locale'] = 'de_DE';

				return $parts;
			}
		);
		$german = $this->build_key();

		$this->assertNotSame(
			$french,
			$german,
			'Two different locales must not share a cache entry.'
		);
	}

	public function testIdenticalFilterValuesProduceIdenticalKeys() {
		$callback = function ( $parts ) {
			$parts['locale'] = 'fr_FR';

			return $parts;
		};

		add_filter( self::FILTER, $callback );
		$first = $this->build_key();

		remove_all_filters( self::FILTER );

		add_filter( self::FILTER, $callback );
		$second = $this->build_key();

		$this->assertSame(
			$first,
			$second,
			'The same filter value must produce the same key so cached responses are actually reused.'
		);
	}

	/**
	 * A filter that returns something other than an array must not take caching
	 * down for the whole site, so the unfiltered parts are used instead.
	 *
	 * @dataProvider nonArrayFilterReturnProvider
	 *
	 * @param mixed $return_value A filter return value that is not an array.
	 */
	public function testNonArrayFilterReturnIsIgnored( $return_value ) {
		add_filter(
			self::FILTER,
			function () use ( $return_value ) {
				return $return_value;
			}
		);

		$this->assertSame(
			self::DEFAULT_KEY,
			$this->build_key(),
			'A filter returning a non-array must be ignored rather than passed to wp_json_encode().'
		);
	}

	public function nonArrayFilterReturnProvider() {
		return [
			'string' => [ 'fr_FR' ],
			'null'   => [ null ],
			'false'  => [ false ],
			'int'    => [ 42 ],
		];
	}
}
