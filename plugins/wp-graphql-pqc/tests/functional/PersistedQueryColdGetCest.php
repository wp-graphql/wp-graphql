<?php
/**
 * HTTP-level tests for persisted query routes.
 *
 * @package WPGraphQL\PQC\Tests\Functional
 */

/**
 * Cold GET to an unknown persisted hash should return JSON error + nonce (SPEC).
 */
class PersistedQueryColdGetCest {

	/**
	 * Pretty permalinks required for graphql/persisted/* rewrites.
	 *
	 * @param FunctionalTester $I Actor.
	 * @return void
	 */
	public function _before( FunctionalTester $I ): void {
		$I->haveOptionInDatabase( 'permalink_structure', '/%postname%/' );
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
		$I->sendGet( "graphql/persisted/{$hash}" );
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
