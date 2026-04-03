<?php
/**
 * HTTP-level tests for persisted query routes.
 *
 * @package WPGraphQL\PQU\Tests\Functional
 */

use TestCase\WPGraphQLPQU\PquFunctionalHttpFixture;

/**
 * Cold GET to an unknown persisted hash should return JSON error + nonce (SPEC).
 */
class PersistedQueryColdGetCest {

	/**
	 * @param FunctionalTester $I Actor.
	 * @return void
	 */
	public function _before( FunctionalTester $I ): void {
		PquFunctionalHttpFixture::ensure_plugins_activated( $I );
	}

	/**
	 * Unknown query hash returns PERSISTED_QUERY_NOT_FOUND and nonce extension.
	 *
	 * @param FunctionalTester $I Actor.
	 * @return void
	 */
	public function cold_get_unknown_hash_returns_not_found_with_nonce( FunctionalTester $I ): void {
		$I->wantTo( 'GET unknown persisted query returns PERSISTED_QUERY_NOT_FOUND and nonce' );
		$hash = str_repeat( 'c', 64 );
		// Query vars exercise the same Router handler as /graphql/persisted/{hash} without depending on Apache rewrites.
		$I->sendGet(
			'/',
			[
				'graphql_persisted_query' => '1',
				'graphql_query_hash'      => $hash,
			]
		);
		$I->seeResponseCodeIs( 200 );
		$I->seeResponseIsJson();
		$I->seeResponseContainsJson(
			[
				'errors' => [
					[
						'extensions' => [
							'code' => 'PERSISTED_QUERY_NOT_FOUND',
						],
					],
				],
			]
		);
		$I->seeResponseContains( 'persistedQueryNonce' );
	}
}
