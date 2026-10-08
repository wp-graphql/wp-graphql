<?php
/**
 * A GraphQL response that carries errors must never be advertised to a cache.
 *
 * The Cache-Control max-age is derived from the saved document or the global setting
 * without looking at the response, so before this was fixed an error response was sent
 * with `max-age=...` like any other. An error response carries no `X-GraphQL-Keys`, so
 * there is nothing to purge it by either: a single failure - the
 * `PersistedQueryNotFound` handshake error every persisted-query client hits before it
 * has sent its first query, a partial response, a resolver blowing up - was replayed to
 * every other client for the whole TTL.
 *
 * These are HTTP-level assertions on purpose: the bug is about a response header, so the
 * test asserts the header that actually goes out over the wire. The functional suite
 * drives a real HTTP client, so anything that has to run inside the request - like
 * `add_filter()` - has no place here; the `graphql_cache_error_responses` opt-out is
 * covered against the real `graphql_response_headers_to_send` filter in
 * tests/wpunit/ErrorResponseCacheControlTest.php.
 */
class ErrorResponseCacheControlCest {

	/**
	 * @var int
	 */
	public $max_age = 30;

	public function _before( FunctionalTester $I ) {
		$I->haveOptionInDatabase( 'graphql_cache_section', [ 'global_max_age' => $this->max_age ] );
	}

	public function _after( FunctionalTester $I ) {
		$I->dontHaveOptionInDatabase( 'graphql_cache_section' );
	}

	/**
	 * The strongest form of the bug: a real configured max-age, and a request that
	 * genuinely errors the way a persisted-query handshake does.
	 */
	public function errorResponseIsNotAdvertisedAsCacheableTest( FunctionalTester $I ) {
		$I->wantTo( 'send no-store, not the configured max-age, for a response carrying errors' );

		$I->sendGet( 'graphql', [ 'queryId' => 'does-not-exist' ] );
		$I->seeResponseContainsJson([
			'errors' => [
				'message' => 'PersistedQueryNotFound',
			],
		]);

		$I->seeHttpHeader( 'Cache-Control', 'no-store' );
	}

	/**
	 * A response that fails validation is an error response too, not just a missing
	 * persisted query.
	 */
	public function invalidQueryIsNotAdvertisedAsCacheableTest( FunctionalTester $I ) {
		$I->wantTo( 'send no-store for a query that fails to validate' );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendPost( 'graphql', json_encode( [
			'query' => 'query { thisFieldDoesNotExist }',
		] ) );
		$I->seeResponseContainsJson([
			'errors' => [
				'message' => 'Cannot query field "thisFieldDoesNotExist" on type "RootQuery".',
			],
		]);

		$I->seeHttpHeader( 'Cache-Control', 'no-store' );
	}

	/**
	 * The regression guard. If the error check swallowed every response, caching would
	 * be off site-wide, which is worse than the bug being fixed.
	 */
	public function cleanResponseStillGetsMaxAgeTest( FunctionalTester $I ) {
		$I->wantTo( 'keep sending the configured max-age for a clean response' );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendPost( 'graphql', json_encode( [
			'query' => 'query { __typename }',
		] ) );
		$I->seeResponseContainsJson([
			'data' => [
				'__typename' => 'RootQuery',
			],
		]);

		$I->seeHttpHeader( 'Cache-Control', 'max-age=30, s-maxage=30, must-revalidate' );
	}

	/**
	 * One Cache-Control header covers the whole batch, so one failing operation makes
	 * the entire response unsafe to store.
	 */
	public function batchWithOneErrorIsNotAdvertisedAsCacheableTest( FunctionalTester $I ) {
		$I->wantTo( 'send no-store when one operation in a batch errors' );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendPost( 'graphql', json_encode( [
			[ 'query' => 'query { __typename }' ],
			[ 'query' => 'query { thisFieldDoesNotExist }' ],
		] ) );
		$I->seeResponseContainsJson([
			'errors' => [
				'message' => 'Cannot query field "thisFieldDoesNotExist" on type "RootQuery".',
			],
		]);

		$I->seeHttpHeader( 'Cache-Control', 'no-store' );
	}

	/**
	 * A batch where every operation succeeds is still cacheable. The guard against the
	 * batch check being too eager.
	 */
	public function cleanBatchStillGetsMaxAgeTest( FunctionalTester $I ) {
		$I->wantTo( 'keep sending the configured max-age when every operation in a batch succeeds' );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendPost( 'graphql', json_encode( [
			[ 'query' => 'query { __typename }' ],
			[ 'query' => 'query { generalSettings { title } }' ],
		] ) );
		$I->dontSeeResponseContainsJson( [ 'errors' ] );

		$I->seeHttpHeader( 'Cache-Control', 'max-age=30, s-maxage=30, must-revalidate' );
	}

	/**
	 * "regardless of configured max-age" - with nothing configured there is no max-age to
	 * send, but the error still must not look cacheable.
	 */
	public function errorResponseWithNoMaxAgeConfiguredTest( FunctionalTester $I ) {
		$I->wantTo( 'send no-store for an error response even when no max-age is configured' );

		$I->dontHaveOptionInDatabase( 'graphql_cache_section' );

		$I->sendGet( 'graphql', [ 'queryId' => 'does-not-exist' ] );
		$I->seeResponseContainsJson([
			'errors' => [
				'message' => 'PersistedQueryNotFound',
			],
		]);

		$I->seeHttpHeader( 'Cache-Control', 'no-store' );
	}

	/**
	 * A clean response with no max-age configured must stay header-free, exactly as it
	 * was before. Sending `no-store` there would turn a site with caching turned off
	 * into a site that tells every shared cache never to store anything.
	 */
	public function cleanResponseWithNoMaxAgeConfiguredTest( FunctionalTester $I ) {
		$I->wantTo( 'send no Cache-Control for a clean response when no max-age is configured' );

		$I->dontHaveOptionInDatabase( 'graphql_cache_section' );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendPost( 'graphql', json_encode( [
			'query' => 'query { __typename }',
		] ) );
		$I->seeResponseContainsJson([
			'data' => [
				'__typename' => 'RootQuery',
			],
		]);

		$I->dontSeeHttpHeader( 'Cache-Control' );
	}
}
