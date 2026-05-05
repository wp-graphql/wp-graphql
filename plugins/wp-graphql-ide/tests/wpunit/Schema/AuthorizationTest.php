<?php
/**
 * Tests for the IDE's GraphQL authorization filters.
 *
 * Two filters together enforce per-user isolation:
 *   - scope_ide_graphql_connections_to_current_user (list-level)
 *   - restrict_ide_post_visibility_to_author       (single-node level)
 *
 * The unit tests here are the load-bearing security guarantee. If any
 * of them go red, an IDE user with manage_graphql_ide can read
 * another user's saved queries — which is exactly the failure mode
 * that motivated both filters in the first place.
 */

namespace Tests\WPGraphQLIDE\Schema;

class AuthorizationTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	private $user_a;
	private $user_b;

	public function setUp(): void {
		parent::setUp();
		$this->clearSchema();

		// Two users with the IDE capability so the test isn't accidentally
		// testing the cap gate — it's testing data isolation at equal
		// permission levels.
		$this->user_a = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		$this->user_b = $this->factory()->user->create( [ 'role' => 'administrator' ] );
	}

	public function tearDown(): void {
		$this->clearSchema();
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function create_ide_query( int $author, string $title = 'Test' ): int {
		return $this->factory()->post->create( [
			'post_type'    => 'graphql_ide_query',
			'post_status'  => 'publish',
			'post_author'  => $author,
			'post_title'   => $title,
			'post_content' => 'query { posts { nodes { id } } }',
		] );
	}

	private function create_ide_history( int $author ): int {
		return $this->factory()->post->create( [
			'post_type'   => 'graphql_ide_history',
			'post_status' => 'publish',
			'post_author' => $author,
		] );
	}

	// ---------------------------------------------------------------
	// Connection scoping — scope_ide_graphql_connections_to_current_user
	// ---------------------------------------------------------------

	public function test_connection_returns_only_current_users_queries() {
		$this->create_ide_query( $this->user_a, 'A doc 1' );
		$this->create_ide_query( $this->user_a, 'A doc 2' );
		$this->create_ide_query( $this->user_b, 'B doc' );

		wp_set_current_user( $this->user_a );
		$response = $this->graphql( [ 'query' => '{ ideQueries { nodes { title } } }' ] );

		$titles = array_column( $response['data']['ideQueries']['nodes'], 'title' );
		$this->assertCount( 2, $titles );
		$this->assertContains( 'A doc 1', $titles );
		$this->assertContains( 'A doc 2', $titles );
		$this->assertNotContains( 'B doc', $titles );
	}

	public function test_connection_returns_empty_for_anonymous_user() {
		$this->create_ide_query( $this->user_a, 'Owned by A' );

		wp_set_current_user( 0 );
		$response = $this->graphql( [ 'query' => '{ ideQueries { nodes { title } } }' ] );

		// Anonymous → author = 0, which matches no posts.
		$this->assertEmpty( $response['data']['ideQueries']['nodes'] );
	}

	public function test_history_connection_is_also_scoped() {
		$this->create_ide_history( $this->user_a );
		$this->create_ide_history( $this->user_b );

		wp_set_current_user( $this->user_a );
		$response = $this->graphql( [ 'query' => '{ ideHistoryEntries { nodes { databaseId author { node { databaseId } } } } }' ] );

		$nodes = $response['data']['ideHistoryEntries']['nodes'];
		$this->assertCount( 1, $nodes );
		$this->assertEquals( $this->user_a, $nodes[0]['author']['node']['databaseId'] );
	}

	public function test_collection_connection_is_not_scoped() {
		// Collections (the taxonomy) are intentionally not scoped — they
		// can be sitewide. Pin the behavior so a future "scope everything"
		// change doesn't accidentally break sharing.
		wp_insert_term( 'sitewide-collection', 'graphql_ide_collection' );

		wp_set_current_user( $this->user_b );
		$response = $this->graphql( [ 'query' => '{ ideCollections { nodes { name } } }' ] );

		$names = array_column( $response['data']['ideCollections']['nodes'], 'name' );
		$this->assertContains( 'sitewide-collection', $names );
	}

	// ---------------------------------------------------------------
	// Single-node visibility — restrict_ide_post_visibility_to_author
	// ---------------------------------------------------------------

	public function test_single_node_lookup_returns_null_for_other_users_query() {
		$post_id = $this->create_ide_query( $this->user_a, 'Owned by A' );

		wp_set_current_user( $this->user_b );
		$response = $this->graphql( [
			'query'     => 'query($id: ID!) { ideQuery(id: $id, idType: DATABASE_ID) { databaseId title } }',
			'variables' => [ 'id' => (string) $post_id ],
		] );

		$this->assertNull( $response['data']['ideQuery'] );
	}

	public function test_single_node_lookup_returns_data_for_owner() {
		$post_id = $this->create_ide_query( $this->user_a, 'Owned by A' );

		wp_set_current_user( $this->user_a );
		$response = $this->graphql( [
			'query'     => 'query($id: ID!) { ideQuery(id: $id, idType: DATABASE_ID) { databaseId title } }',
			'variables' => [ 'id' => (string) $post_id ],
		] );

		$this->assertEquals( $post_id, $response['data']['ideQuery']['databaseId'] );
		$this->assertEquals( 'Owned by A', $response['data']['ideQuery']['title'] );
	}

	public function test_relay_node_lookup_is_also_gated() {
		$post_id   = $this->create_ide_query( $this->user_a );
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', (string) $post_id );

		wp_set_current_user( $this->user_b );
		$response = $this->graphql( [
			'query'     => 'query($id: ID!) { node(id: $id) { __typename ... on IdeQuery { title } } }',
			'variables' => [ 'id' => $global_id ],
		] );

		// Visibility-marked-private resolves to null at the node field.
		$this->assertNull( $response['data']['node'] );
	}

	public function test_history_entry_single_node_is_gated() {
		$post_id = $this->create_ide_history( $this->user_a );

		wp_set_current_user( $this->user_b );
		$response = $this->graphql( [
			'query'     => 'query($id: ID!) { ideHistoryEntry(id: $id, idType: DATABASE_ID) { databaseId } }',
			'variables' => [ 'id' => (string) $post_id ],
		] );

		$this->assertNull( $response['data']['ideHistoryEntry'] );
	}

	public function test_anonymous_single_node_lookup_is_gated() {
		$post_id = $this->create_ide_query( $this->user_a );

		wp_set_current_user( 0 );
		$response = $this->graphql( [
			'query'     => 'query($id: ID!) { ideQuery(id: $id, idType: DATABASE_ID) { databaseId } }',
			'variables' => [ 'id' => (string) $post_id ],
		] );

		$this->assertNull( $response['data']['ideQuery'] );
	}

	public function test_visibility_filter_does_not_affect_non_ide_post_types() {
		// Make sure we didn't accidentally privatize regular Posts.
		$post_id = $this->factory()->post->create( [
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_author' => $this->user_a,
			'post_title'  => 'Regular post',
		] );

		wp_set_current_user( $this->user_b );
		$response = $this->graphql( [
			'query'     => 'query($id: ID!) { post(id: $id, idType: DATABASE_ID) { title } }',
			'variables' => [ 'id' => (string) $post_id ],
		] );

		$this->assertEquals( 'Regular post', $response['data']['post']['title'] );
	}
}
