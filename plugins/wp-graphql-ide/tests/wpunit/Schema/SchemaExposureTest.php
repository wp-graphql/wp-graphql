<?php
/**
 * Tests for the IDE's GraphQL schema exposure.
 *
 * As of 5.0 the IDE registers no GraphQL types of its own. Saved
 * documents are owned by Smart Cache (`graphqlDocument` /
 * `graphqlDocumentGroup`); execution history is browser-local
 * (localStorage) and has no schema surface.
 *
 * These tests lock that contract: nothing IDE-specific should be in
 * the schema. A regression here means someone re-registered one of
 * the removed 4.x types instead of routing through Smart Cache.
 */

namespace Tests\WPGraphQLIDE\Schema;

class SchemaExposureTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function setUp(): void {
		parent::setUp();

		// IDE schema fields are registered on `graphql_register_types`,
		// which fires when WPGraphQL walks the registry. Clearing the
		// schema between tests forces a re-walk so each test sees a
		// fresh registry state.
		$this->clearSchema();
	}

	public function tearDown(): void {
		$this->clearSchema();
		parent::tearDown();
	}

	public function test_removed_ide_types_are_not_in_schema() {
		// Direct TypeRegistry lookups don't fire registration eagerly,
		// so we go through introspection — which exercises the same
		// path consumers use and is the actual contract.
		$query    = '{ __schema { types { name } } }';
		$response = $this->graphql( [ 'query' => $query ] );
		$names    = array_column( $response['data']['__schema']['types'], 'name' );

		// 5.0 removals — surface a clear failure if any creep back into
		// the schema (would mean someone re-registered the post type or
		// schema name).
		$this->assertNotContains( 'IdeQuery', $names, 'IdeQuery was removed in 5.0; Smart Cache\'s GraphqlDocument is the canonical type.' );
		$this->assertNotContains( 'IdeCollection', $names, 'IdeCollection was removed in 5.0; Smart Cache\'s GraphqlDocumentGroup is the canonical type.' );
	}

	public function test_removed_ide_root_fields_are_not_in_schema() {
		$query    = '{ __schema { queryType { fields { name } } } }';
		$response = $this->graphql( [ 'query' => $query ] );
		$names    = array_column( $response['data']['__schema']['queryType']['fields'], 'name' );

		// 5.0 removals.
		$this->assertNotContains( 'ideQuery', $names );
		$this->assertNotContains( 'ideQueries', $names );
		$this->assertNotContains( 'ideCollection', $names );
		$this->assertNotContains( 'ideCollections', $names );
	}
}
