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
	 * Functional HTTP tests hit the real web container; Codeception WPLoader activation does not update `active_plugins`
	 * in the database. Ensure GraphQL + Smart Cache + PQC are active so Router and query vars register.
	 *
	 * @param FunctionalTester $I Actor.
	 * @return void
	 */
	public function _before( FunctionalTester $I ): void {
		$I->haveOptionInDatabase(
			'active_plugins',
			[
				'wp-graphql/wp-graphql.php',
				'wp-graphql-smart-cache/wp-graphql-smart-cache.php',
				'wp-graphql-pqc/wp-graphql-pqc.php',
			]
		);
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
