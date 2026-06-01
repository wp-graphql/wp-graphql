<?php
/**
 * Tests for the IDE's GraphQL authorization filters.
 *
 * Two filters together enforce per-user isolation on Smart Cache's
 * `graphql_document` post type (the IDE's canonical document owner as
 * of 5.0):
 *   - scope_graphql_connections (list-level — filters connection args)
 *   - restrict_post_visibility  (single-node level — marks foreign
 *     records private at the Model layer)
 *
 * These tests are the load-bearing security guarantee. If any go red,
 * a user holding `manage_graphql_ide` can read another user's saved
 * documents through the GraphQL schema — which is exactly the failure
 * mode that motivated both filters in the first place.
 *
 * Execution history has no GraphQL surface as of 5.0 (localStorage
 * only), so there's nothing to authorize on that side.
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

	/**
	 * Build a graphql_document post. Each document gets a unique query
	 * string keyed off `$title` so Smart Cache's `validate_and_pre_save_cb`
	 * doesn't reject duplicates by normalized-hash collision across
	 * fixtures.
	 */
	private function create_ide_document( int $author, string $title = 'Test' ): int {
		// Spaces in the alias keep the query parseable; the title goes
		// into a `name` alias so the AST hash is unique per fixture.
		$query = sprintf( 'query %s { posts { nodes { id } } }', preg_replace( '/[^A-Za-z0-9]/', '_', $title ) );

		return $this->factory()->post->create( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
			'post_author'  => $author,
			'post_title'   => $title,
			'post_content' => $query,
		] );
	}

	// ---------------------------------------------------------------
	// Connection scoping — scope_graphql_connections
	// ---------------------------------------------------------------

	public function test_connection_returns_only_current_users_documents() {
		$this->create_ide_document( $this->user_a, 'A doc 1' );
		$this->create_ide_document( $this->user_a, 'A doc 2' );
		$this->create_ide_document( $this->user_b, 'B doc' );

		wp_set_current_user( $this->user_a );
		$response = $this->graphql( [ 'query' => '{ graphqlDocuments { nodes { title } } }' ] );

		$titles = array_column( $response['data']['graphqlDocuments']['nodes'], 'title' );
		$this->assertCount( 2, $titles );
		$this->assertContains( 'A doc 1', $titles );
		$this->assertContains( 'A doc 2', $titles );
		$this->assertNotContains( 'B doc', $titles );
	}

	public function test_connection_returns_empty_for_anonymous_user() {
		$this->create_ide_document( $this->user_a, 'Owned by A' );

		wp_set_current_user( 0 );
		$response = $this->graphql( [ 'query' => '{ graphqlDocuments { nodes { title } } }' ] );

		// Anonymous → author = 0, which matches no posts.
		$this->assertEmpty( $response['data']['graphqlDocuments']['nodes'] );
	}

	// ---------------------------------------------------------------
	// Single-node visibility — restrict_post_visibility
	// ---------------------------------------------------------------

	public function test_single_node_lookup_returns_null_for_other_users_document() {
		$post_id = $this->create_ide_document( $this->user_a, 'Owned by A' );

		wp_set_current_user( $this->user_b );
		$response = $this->graphql( [
			'query'     => 'query($id: ID!) { graphqlDocument(id: $id, idType: DATABASE_ID) { databaseId title } }',
			'variables' => [ 'id' => (string) $post_id ],
		] );

		$this->assertNull( $response['data']['graphqlDocument'] );
	}

	public function test_single_node_lookup_returns_data_for_owner() {
		$post_id = $this->create_ide_document( $this->user_a, 'Owned by A' );

		wp_set_current_user( $this->user_a );
		$response = $this->graphql( [
			'query'     => 'query($id: ID!) { graphqlDocument(id: $id, idType: DATABASE_ID) { databaseId title } }',
			'variables' => [ 'id' => (string) $post_id ],
		] );

		$this->assertEquals( $post_id, $response['data']['graphqlDocument']['databaseId'] );
		$this->assertEquals( 'Owned by A', $response['data']['graphqlDocument']['title'] );
	}

	public function test_relay_node_lookup_is_also_gated() {
		$post_id   = $this->create_ide_document( $this->user_a, 'Relay node' );
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'post', (string) $post_id );

		wp_set_current_user( $this->user_b );
		$response = $this->graphql( [
			'query'     => 'query($id: ID!) { node(id: $id) { __typename ... on GraphqlDocument { title } } }',
			'variables' => [ 'id' => $global_id ],
		] );

		// Visibility-marked-private resolves to null at the node field.
		$this->assertNull( $response['data']['node'] );
	}

	public function test_anonymous_single_node_lookup_is_gated() {
		$post_id = $this->create_ide_document( $this->user_a, 'Anon gated' );

		wp_set_current_user( 0 );
		$response = $this->graphql( [
			'query'     => 'query($id: ID!) { graphqlDocument(id: $id, idType: DATABASE_ID) { databaseId } }',
			'variables' => [ 'id' => (string) $post_id ],
		] );

		$this->assertNull( $response['data']['graphqlDocument'] );
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
