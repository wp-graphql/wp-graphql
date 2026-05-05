<?php
/**
 * Tests for the IDE's GraphQL schema exposure.
 *
 * Locks the public schema contract: type names, custom field
 * registration, scalar shapes. Renaming any of these is a breaking
 * change for downstream consumers, so the tests intentionally read
 * like a contract: assert the names, not just "something registered".
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

	public function test_ide_types_are_registered_in_schema() {
		// Direct TypeRegistry lookups don't fire registration eagerly,
		// so we go through introspection — which exercises the same
		// path consumers use and is the actual contract.
		$query = '{ __schema { types { name } } }';
		$response = $this->graphql( [ 'query' => $query ] );
		$names = array_column( $response['data']['__schema']['types'], 'name' );

		$this->assertContains( 'IdeQuery', $names );
		$this->assertContains( 'IdeHistoryEntry', $names );
		$this->assertContains( 'IdeCollection', $names );
	}

	public function test_ide_query_root_field_is_registered() {
		$query = '{ __schema { queryType { fields { name } } } }';
		$response = $this->graphql( [ 'query' => $query ] );
		$names = array_column( $response['data']['__schema']['queryType']['fields'], 'name' );

		$this->assertContains( 'ideQuery', $names );
		$this->assertContains( 'ideQueries', $names );
		$this->assertContains( 'ideHistoryEntry', $names );
		$this->assertContains( 'ideHistoryEntries', $names );
		$this->assertContains( 'ideCollection', $names );
		$this->assertContains( 'ideCollections', $names );
	}

	public function test_ide_query_has_custom_meta_fields() {
		$query = '{ __type(name: "IdeQuery") { fields { name type { name kind ofType { name } } } } }';
		$response = $this->graphql( [ 'query' => $query ] );
		$fields = $response['data']['__type']['fields'];

		$by_name = [];
		foreach ( $fields as $field ) {
			$by_name[ $field['name'] ] = $field;
		}

		$this->assertArrayHasKey( 'queryString', $by_name, 'IdeQuery should expose queryString' );
		$this->assertArrayHasKey( 'variables', $by_name, 'IdeQuery should expose variables' );
		$this->assertArrayHasKey( 'headers', $by_name, 'IdeQuery should expose headers' );

		// All three are nullable strings — guard the contract.
		$this->assertEquals( 'String', $by_name['queryString']['type']['name'] );
		$this->assertEquals( 'String', $by_name['variables']['type']['name'] );
		$this->assertEquals( 'String', $by_name['headers']['type']['name'] );
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

	public function test_ide_query_query_string_resolves_post_content() {
		$user = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user );

		$post_id = $this->factory()->post->create( [
			'post_type'    => 'graphql_ide_query',
			'post_status'  => 'publish',
			'post_author'  => $user,
			'post_title'   => 'Test',
			'post_content' => 'query Foo { posts { nodes { id } } }',
		] );

		$response = $this->graphql( [
			'query'     => 'query($id: ID!) { ideQuery(id: $id, idType: DATABASE_ID) { databaseId queryString } }',
			'variables' => [ 'id' => (string) $post_id ],
		] );

		$this->assertEquals( $post_id, $response['data']['ideQuery']['databaseId'] );
		$this->assertEquals( 'query Foo { posts { nodes { id } } }', $response['data']['ideQuery']['queryString'] );
	}

	public function test_ide_query_meta_fields_resolve_post_meta() {
		$user = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user );

		$post_id = $this->factory()->post->create( [
			'post_type'   => 'graphql_ide_query',
			'post_status' => 'publish',
			'post_author' => $user,
		] );
		update_post_meta( $post_id, '_graphql_ide_variables', '{"first":10}' );
		update_post_meta( $post_id, '_graphql_ide_headers', '{"X-Test":"1"}' );

		$response = $this->graphql( [
			'query'     => 'query($id: ID!) { ideQuery(id: $id, idType: DATABASE_ID) { variables headers } }',
			'variables' => [ 'id' => (string) $post_id ],
		] );

		$this->assertEquals( '{"first":10}', $response['data']['ideQuery']['variables'] );
		$this->assertEquals( '{"X-Test":"1"}', $response['data']['ideQuery']['headers'] );
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
