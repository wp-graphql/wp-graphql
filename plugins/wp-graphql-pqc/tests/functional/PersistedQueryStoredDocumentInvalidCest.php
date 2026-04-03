<?php
/**
 * Stored document fails GraphQL parse → PERSISTED_QUERY_DOCUMENT_INVALID.
 *
 * @package WPGraphQL\PQC\Tests\Functional
 */

use TestCase\WPGraphQLPQC\PqcFunctionalHttpFixture;

/**
 * Invalid stored document row (corrupt GraphQL text).
 */
class PersistedQueryStoredDocumentInvalidCest {

	private const QUERY_HASH = 'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff';

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
	 * Corrupt document in index returns GraphQL-style error, not a theme 500 page.
	 *
	 * @param FunctionalTester $I Actor.
	 * @return void
	 */
	public function warm_get_invalid_stored_document_returns_document_invalid_code( FunctionalTester $I ): void {
		$I->wantTo( 'invalid stored document returns PERSISTED_QUERY_DOCUMENT_INVALID' );

		$docs = $I->grabPrefixedTableNameFor( 'wpgraphql_pqc_documents' );
		$exe  = $I->grabPrefixedTableNameFor( 'wpgraphql_pqc_executions' );

		$I->haveInDatabase(
			$docs,
			[
				'query_hash'     => self::QUERY_HASH,
				'query_document' => 'this is not valid graphql {{{',
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
				'errors' => [
					[
						'extensions' => [
							'code' => 'PERSISTED_QUERY_DOCUMENT_INVALID',
						],
					],
				],
			]
		);
	}
}
