<?php
/**
 * Tests for the IDE's REST permission and authorization layer.
 *
 * Three guards in front of every IDE REST request:
 *   - manage_graphql_ide on every /wpgraphql-ide/v1/* and /wp/v2/graphql_document
 *     (Smart Cache's saved-document post type, as of 5.0) and
 *     /wp/v2/graphql-ide-history route
 *   - per-user authorship on /wp/v2/graphql_document/{id} and
 *     /wp/v2/graphql-ide-history/{id}
 *   - per-user filtering of list queries
 *
 * If any of these go red, an attacker (or just a curious authenticated
 * subscriber) can read/write IDE data they shouldn't.
 */

namespace Tests\WPGraphQLIDE\Rest;

class PermissionsTest extends \Codeception\TestCase\WPTestCase {

	private $admin_a;
	private $admin_b;
	private $subscriber;
	private $rest_server;

	public function setUp(): void {
		parent::setUp();

		$this->admin_a    = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		$this->admin_b    = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		$this->subscriber = $this->factory()->user->create( [ 'role' => 'subscriber' ] );

		// Spin up a fresh REST server so register_rest_route() picks up
		// our routes — without this the routes don't dispatch under
		// WPTestCase.
		global $wp_rest_server;
		$wp_rest_server    = new \WP_REST_Server();
		$this->rest_server = $wp_rest_server;
		do_action( 'rest_api_init' );
	}

	public function tearDown(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Dispatch a REST request and return a `WP_REST_Response`.
	 *
	 * On WP 6.1 the server returns `WP_Error` directly when a
	 * permission callback rejects the request; later versions
	 * normalize that to a `WP_REST_Response` with the appropriate
	 * status. `rest_ensure_response()` handles the conversion in both
	 * directions, so tests can rely on a single shape regardless of
	 * the WP version they're running against.
	 *
	 * @return \WP_REST_Response
	 */
	private function dispatch( string $method, string $path, array $body = [] ) {
		$request = new \WP_REST_Request( $method, $path );
		if ( ! empty( $body ) ) {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}
		$result = $this->rest_server->dispatch( $request );
		if ( $result instanceof \WP_Error ) {
			$status = $result->get_error_data();
			$status = is_array( $status ) && isset( $status['status'] )
				? (int) $status['status']
				: 500;
			$response = new \WP_REST_Response( $result, $status );
			return $response;
		}
		return $result;
	}

	/**
	 * Create a graphql_document under a specific author. Each doc gets
	 * a unique GraphQL body so Smart Cache's `validate_and_pre_save_cb`
	 * (which hashes content and rejects normalized duplicates) doesn't
	 * trip up tests creating multiple fixtures.
	 */
	private function create_doc( int $author ): int {
		static $counter = 0;
		$counter++;

		return $this->factory()->post->create( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
			'post_author'  => $author,
			'post_content' => sprintf( 'query Doc%d { posts { nodes { id } } }', $counter ),
		] );
	}

	// ---------------------------------------------------------------
	// Capability gate (enforce_rest_permissions)
	// ---------------------------------------------------------------

	public function test_subscriber_cannot_list_documents() {
		wp_set_current_user( $this->subscriber );
		$response = $this->dispatch( 'GET', '/wp/v2/graphql_document' );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_subscriber_cannot_list_history() {
		wp_set_current_user( $this->subscriber );
		$response = $this->dispatch( 'GET', '/wp/v2/graphql-ide-history' );
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_anonymous_cannot_call_custom_routes() {
		// Sample the four remaining custom routes — they share the same
		// permission_callback shape (wpgraphql_ide_user_can). The publish
		// and cascade-collection routes were removed in 5.0; they no
		// longer have anything to test here.
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->dispatch( 'GET', '/wpgraphql-ide/v1/documents/export' )->get_status() );
		$this->assertSame( 401, $this->dispatch( 'POST', '/wpgraphql-ide/v1/documents/reorder', [ 'order' => [] ] )->get_status() );
		$this->assertSame( 401, $this->dispatch( 'POST', '/wpgraphql-ide/v1/collections/reorder', [ 'order' => [] ] )->get_status() );
	}

	public function test_admin_can_call_custom_routes() {
		wp_set_current_user( $this->admin_a );
		$this->assertSame( 200, $this->dispatch( 'GET', '/wpgraphql-ide/v1/documents/export' )->get_status() );
	}

	// ---------------------------------------------------------------
	// Per-user list scoping (scope_rest_queries)
	// ---------------------------------------------------------------

	public function test_admin_only_sees_own_documents_in_list() {
		$mine = $this->create_doc( $this->admin_a );
		$theirs = $this->create_doc( $this->admin_b );

		wp_set_current_user( $this->admin_a );
		$response = $this->dispatch( 'GET', '/wp/v2/graphql_document' );

		$this->assertSame( 200, $response->get_status() );
		$ids = array_column( $response->get_data(), 'id' );
		$this->assertContains( $mine, $ids );
		$this->assertNotContains( $theirs, $ids );
	}

	// ---------------------------------------------------------------
	// Per-document author check (restrict_document_response)
	// ---------------------------------------------------------------

	public function test_admin_cannot_read_other_admins_document_by_id() {
		$theirs = $this->create_doc( $this->admin_b );

		wp_set_current_user( $this->admin_a );
		$response = $this->dispatch( 'GET', '/wp/v2/graphql_document/' . $theirs );

		// restrict_document_response returns WP_Error('rest_forbidden', ..., 403)
		// when the requester isn't the author. Empty body / 403.
		$this->assertSame( 403, $response->get_status() );
	}

	public function test_admin_can_read_own_document_by_id() {
		$mine = $this->create_doc( $this->admin_a );

		wp_set_current_user( $this->admin_a );
		$response = $this->dispatch( 'GET', '/wp/v2/graphql_document/' . $mine );

		$this->assertSame( 200, $response->get_status() );
		$this->assertEquals( $mine, $response->get_data()['id'] );
	}

	// ---------------------------------------------------------------
	// Reorder routes — payload validation
	// ---------------------------------------------------------------

	public function test_reorder_documents_rejects_missing_order() {
		wp_set_current_user( $this->admin_a );
		$response = $this->dispatch( 'POST', '/wpgraphql-ide/v1/documents/reorder', [] );
		$this->assertSame( 400, $response->get_status() );
	}

	public function test_reorder_documents_only_touches_own_posts() {
		$mine   = $this->create_doc( $this->admin_a );
		$theirs = $this->create_doc( $this->admin_b );

		wp_update_post( [ 'ID' => $mine,   'menu_order' => 0 ] );
		wp_update_post( [ 'ID' => $theirs, 'menu_order' => 0 ] );

		wp_set_current_user( $this->admin_a );
		$response = $this->dispatch( 'POST', '/wpgraphql-ide/v1/documents/reorder', [
			'order' => [ $mine, $theirs ],
		] );

		$this->assertSame( 200, $response->get_status() );

		// Mine moved (position 0 → still 0), theirs untouched.
		$this->assertEquals( 0, get_post( $mine )->menu_order );
		$this->assertEquals( 0, get_post( $theirs )->menu_order );

		// Now try to push mine to position 1.
		$this->dispatch( 'POST', '/wpgraphql-ide/v1/documents/reorder', [
			'order' => [ $theirs, $mine ],
		] );
		$this->assertEquals( 1, get_post( $mine )->menu_order, 'My doc should move' );
		$this->assertEquals( 0, get_post( $theirs )->menu_order, 'Other admin\'s doc should not move' );
	}

	public function test_reorder_collections_persists_per_user_order() {
		// Collections are Smart Cache's `graphql_document_group` taxonomy
		// terms as of 5.0.
		$term_a = wp_insert_term( 'col-a', 'graphql_document_group' )['term_id'];
		$term_b = wp_insert_term( 'col-b', 'graphql_document_group' )['term_id'];

		wp_set_current_user( $this->admin_a );
		$response = $this->dispatch( 'POST', '/wpgraphql-ide/v1/collections/reorder', [
			'order' => [ $term_b, $term_a ],
		] );

		$this->assertSame( 200, $response->get_status() );
		$this->assertEquals(
			[ $term_b, $term_a ],
			get_user_meta( $this->admin_a, 'wpgraphql_ide_collection_order', true )
		);
	}

	// ---------------------------------------------------------------
	// Import payload validation
	// ---------------------------------------------------------------

	public function test_import_rejects_invalid_payload() {
		wp_set_current_user( $this->admin_a );
		$response = $this->dispatch( 'POST', '/wpgraphql-ide/v1/documents/import', [] );
		$this->assertSame( 400, $response->get_status() );

		$response = $this->dispatch( 'POST', '/wpgraphql-ide/v1/documents/import', [
			'collections' => [],
		] );
		$this->assertSame( 400, $response->get_status() );
	}
}
