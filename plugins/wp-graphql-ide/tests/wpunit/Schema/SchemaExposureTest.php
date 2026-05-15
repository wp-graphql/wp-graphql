<?php
/**
 * Tests for the IDE's GraphQL schema exposure.
 *
 * Locks the public schema contract: type names, custom field
 * registration, scalar shapes. Renaming any of these is a breaking
 * change for downstream consumers, so the tests intentionally read
 * like a contract: assert the names, not just "something registered".
 *
 * As of 5.0 the IDE no longer registers its own document type; the
 * `IdeQuery` / `IdeQueries` / `IdeCollection` / `IdeCollections` types
 * are retired and consumers query Smart Cache's `graphqlDocument` /
 * `graphqlDocumentGroup` schema directly. The IDE still owns the
 * `IdeHistoryEntry` execution-log surface, so the exposure tests focus
 * there.
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

	public function test_ide_history_type_is_registered_in_schema() {
		// Direct TypeRegistry lookups don't fire registration eagerly,
		// so we go through introspection — which exercises the same
		// path consumers use and is the actual contract.
		$query = '{ __schema { types { name } } }';
		$response = $this->graphql( [ 'query' => $query ] );
		$names = array_column( $response['data']['__schema']['types'], 'name' );

		$this->assertContains( 'IdeHistoryEntry', $names );

		// 5.0 removals — surface a clear failure if the type creeps back
		// into the schema (would mean someone re-registered the post
		// type or the schema name shadowing the removed type).
		$this->assertNotContains( 'IdeQuery', $names, 'IdeQuery was removed in 5.0; Smart Cache\'s GraphqlDocument is the canonical type.' );
		$this->assertNotContains( 'IdeCollection', $names, 'IdeCollection was removed in 5.0; Smart Cache\'s GraphqlDocumentGroup is the canonical type.' );
	}

	public function test_ide_history_root_fields_are_registered() {
		$query = '{ __schema { queryType { fields { name } } } }';
		$response = $this->graphql( [ 'query' => $query ] );
		$names = array_column( $response['data']['__schema']['queryType']['fields'], 'name' );

		$this->assertContains( 'ideHistoryEntry', $names );
		$this->assertContains( 'ideHistoryEntries', $names );

		// 5.0 removals.
		$this->assertNotContains( 'ideQuery', $names );
		$this->assertNotContains( 'ideQueries', $names );
		$this->assertNotContains( 'ideCollection', $names );
		$this->assertNotContains( 'ideCollections', $names );
	}

	public function test_ide_history_entry_has_custom_meta_fields() {
		$query = '{ __type(name: "IdeHistoryEntry") { fields { name type { name kind ofType { name } } } } }';
		$response = $this->graphql( [ 'query' => $query ] );
		$fields = $response['data']['__type']['fields'];

		$by_name = [];
		foreach ( $fields as $field ) {
			$by_name[ $field['name'] ] = $field;
		}

		$expected = [
			'queryString'     => 'String',
			'variables'       => 'String',
			'headers'         => 'String',
			'durationMs'      => 'Int',
			'executionStatus' => 'String',
			'documentId'      => 'Int',
			'isAuthenticated' => 'Boolean',
			'httpMethod'      => 'String',
		];

		foreach ( $expected as $field_name => $expected_type ) {
			$this->assertArrayHasKey( $field_name, $by_name, "IdeHistoryEntry should expose {$field_name}" );
			$this->assertEquals(
				$expected_type,
				$by_name[ $field_name ]['type']['name'],
				"IdeHistoryEntry.{$field_name} should be {$expected_type}"
			);
		}
	}

	public function test_ide_history_entry_typed_meta_fields() {
		$user = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user );

		$post_id = $this->factory()->post->create( [
			'post_type'   => 'graphql_ide_history',
			'post_status' => 'publish',
			'post_author' => $user,
		] );
		update_post_meta( $post_id, '_graphql_ide_duration_ms', '142' );
		update_post_meta( $post_id, '_graphql_ide_status', 'success' );
		update_post_meta( $post_id, '_graphql_ide_document_id', '99' );
		update_post_meta( $post_id, '_graphql_ide_is_authenticated', '1' );
		update_post_meta( $post_id, '_graphql_ide_http_method', 'POST' );

		$response = $this->graphql( [
			'query'     => 'query($id: ID!) { ideHistoryEntry(id: $id, idType: DATABASE_ID) { durationMs executionStatus documentId isAuthenticated httpMethod } }',
			'variables' => [ 'id' => (string) $post_id ],
		] );

		$entry = $response['data']['ideHistoryEntry'];
		// Typed casts are the contract: durationMs/documentId are Int, isAuthenticated is Boolean.
		$this->assertSame( 142, $entry['durationMs'] );
		$this->assertSame( 'success', $entry['executionStatus'] );
		$this->assertSame( 99, $entry['documentId'] );
		$this->assertTrue( $entry['isAuthenticated'] );
		$this->assertSame( 'POST', $entry['httpMethod'] );
	}

	public function test_ide_history_entry_is_authenticated_handles_zero_meta() {
		// Boolean cast goes through (int) first so '0' is falsy. This is
		// the explicit comment in the resolver — pin the behavior so a
		// future "simplification" doesn't break it.
		$user = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user );

		$post_id = $this->factory()->post->create( [
			'post_type'   => 'graphql_ide_history',
			'post_status' => 'publish',
			'post_author' => $user,
		] );
		update_post_meta( $post_id, '_graphql_ide_is_authenticated', '0' );

		$response = $this->graphql( [
			'query'     => 'query($id: ID!) { ideHistoryEntry(id: $id, idType: DATABASE_ID) { isAuthenticated } }',
			'variables' => [ 'id' => (string) $post_id ],
		] );

		$this->assertFalse( $response['data']['ideHistoryEntry']['isAuthenticated'] );
	}
}
