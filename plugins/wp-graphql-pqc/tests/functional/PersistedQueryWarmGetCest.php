<?php
/**
 * Warm persisted GET: store has document + execution; expect live graphql() JSON.
 *
 * @package WPGraphQL\PQC\Tests\Functional
 */

use TestCase\WPGraphQLPQC\PqcFunctionalHttpFixture;

/**
 * Warm GET path (query vars) after seeding documents + executions tables.
 */
class PersistedQueryWarmGetCest {

	private const QUERY_HASH = '8d8f7365e9e86fa8e3313fcaf2131b801eafe9549de22373089cf27511858b39';

	/**
	 * Document stored for warm GET (parseable; hash above is from {@see Hasher::hash_query()} on this text).
	 */
	private const STORED_DOCUMENT = 'query { __typename }';

	/**
	 * @param FunctionalTester $I Actor.
	 * @return void
	 */
	public function _before( FunctionalTester $I ): void {
		PqcFunctionalHttpFixture::ensure_plugins_activated( $I );
	}

	/**
	 * @param FunctionalTester $I Actor.
	 * @return void
	 */
	public function _after( FunctionalTester $I ): void {
		$docs = $I->grabPrefixedTableNameFor( 'wpgraphql_pqc_documents' );
		$exe  = $I->grabPrefixedTableNameFor( 'wpgraphql_pqc_executions' );
		$I->dontHaveInDatabase( $exe, [ 'query_hash' => self::QUERY_HASH ] );
		$I->dontHaveInDatabase( $docs, [ 'query_hash' => self::QUERY_HASH ] );
	}

	/**
	 * Stored valid document executes and returns GraphQL data (root __typename).
	 *
	 * @param FunctionalTester $I Actor.
	 * @return void
	 */
	public function warm_get_executes_stored_document( FunctionalTester $I ): void {
		$I->wantTo( 'warm GET runs graphql() for stored document + execution row' );

		$docs = $I->grabPrefixedTableNameFor( 'wpgraphql_pqc_documents' );
		$exe  = $I->grabPrefixedTableNameFor( 'wpgraphql_pqc_executions' );

		$I->haveInDatabase(
			$docs,
			[
				'query_hash'     => self::QUERY_HASH,
				'query_document' => self::STORED_DOCUMENT,
			]
		);
		$I->haveInDatabase(
			$exe,
			[
				'query_hash'     => self::QUERY_HASH,
				'variables_hash' => '',
				'url'            => '/graphql/persisted/' . self::QUERY_HASH,
				'variables'      => '',
			]
		);

		$I->sendGet(
			'/',
			[
				'graphql_persisted_query' => '1',
				'graphql_query_hash'      => self::QUERY_HASH,
			]
		);
		$I->seeResponseCodeIs( 200 );
		$I->seeResponseIsJson();
		$I->seeResponseContainsJson(
			[
				'data' => [
					'__typename' => 'RootQuery',
				],
			]
		);
	}
}
