<?php
/**
 * HTTP tests for Router validation before GetHandler (invalid hash formats).
 *
 * @package WPGraphQL\PQC\Tests\Functional
 */

use TestCase\WPGraphQLPQC\PqcFunctionalHttpFixture;

/**
 * Invalid persisted URL parameters return GraphQL-style JSON errors (SPEC / Router).
 */
class PersistedQueryRouterValidationCest {

	/**
	 * @param FunctionalTester $I Actor.
	 * @return void
	 */
	public function _before( FunctionalTester $I ): void {
		PqcFunctionalHttpFixture::ensure_plugins_activated( $I );
	}

	/**
	 * Non-hex or wrong-length query hash → INVALID_QUERY_HASH.
	 *
	 * @param FunctionalTester $I Actor.
	 * @return void
	 */
	public function get_invalid_query_hash_returns_invalid_query_hash( FunctionalTester $I ): void {
		$I->wantTo( 'reject invalid persisted query hash format with JSON error' );
		$bad = str_repeat( 'g', 64 );
		$I->sendGet(
			'/',
			[
				'graphql_persisted_query' => '1',
				'graphql_query_hash'      => $bad,
			]
		);
		$I->seeResponseCodeIs( 200 );
		$I->seeResponseIsJson();
		$I->seeResponseContainsJson(
			[
				'errors' => [
					[
						'extensions' => [
							'code' => 'INVALID_QUERY_HASH',
						],
					],
				],
			]
		);
	}

	/**
	 * Wrong-length query hash → INVALID_QUERY_HASH.
	 *
	 * @param FunctionalTester $I Actor.
	 * @return void
	 */
	public function get_short_query_hash_returns_invalid_query_hash( FunctionalTester $I ): void {
		$I->wantTo( 'reject short query hash with JSON error' );
		$I->sendGet(
			'/',
			[
				'graphql_persisted_query' => '1',
				'graphql_query_hash'      => str_repeat( 'a', 63 ),
			]
		);
		$I->seeResponseCodeIs( 200 );
		$I->seeResponseIsJson();
		$I->seeResponseContainsJson(
			[
				'errors' => [
					[
						'extensions' => [
							'code' => 'INVALID_QUERY_HASH',
						],
					],
				],
			]
		);
	}

	/**
	 * Valid query hash but invalid variables hash → INVALID_VARIABLES_HASH.
	 *
	 * @param FunctionalTester $I Actor.
	 * @return void
	 */
	public function get_invalid_variables_hash_returns_invalid_variables_hash( FunctionalTester $I ): void {
		$I->wantTo( 'reject invalid variables hash with JSON error' );
		$query_hash     = str_repeat( 'a', 64 );
		$variables_hash = str_repeat( 'z', 64 );
		$I->sendGet(
			'/',
			[
				'graphql_persisted_query' => '1',
				'graphql_query_hash'      => $query_hash,
				'graphql_variables_hash'  => $variables_hash,
			]
		);
		$I->seeResponseCodeIs( 200 );
		$I->seeResponseIsJson();
		$I->seeResponseContainsJson(
			[
				'errors' => [
					[
						'extensions' => [
							'code' => 'INVALID_VARIABLES_HASH',
						],
					],
				],
			]
		);
	}
}
