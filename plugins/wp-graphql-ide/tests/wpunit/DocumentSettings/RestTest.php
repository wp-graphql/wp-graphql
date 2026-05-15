<?php
/**
 * Tests that the documentSettings REST field reads, writes, and gates by author.
 *
 * Mirrors patterns in tests/wpunit/Rest/PermissionsTest.php (REST server setup,
 * factory-based document creation, dispatch helper).
 *
 * @package WPGraphQLIDE\DocumentSettings
 */

namespace Tests\WPGraphQLIDE\DocumentSettings;

class RestTest extends \Codeception\TestCase\WPTestCase {

	private $admin_a;
	private $admin_b;
	private $rest_server;

	public function setUp(): void {
		parent::setUp();

		$this->admin_a = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		$this->admin_b = $this->factory()->user->create( [ 'role' => 'administrator' ] );

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

	private function dispatch( string $method, string $path, array $body = [] ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, $path );
		if ( ! empty( $body ) ) {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}
		return $this->rest_server->dispatch( $request );
	}

	private function create_doc( int $author ): int {
		// Explicit empty post_excerpt — the factory otherwise seeds a
		// "Post excerpt N" string, which would conflict with our assertion
		// that description defaults to empty for a brand-new doc.
		//
		// Each fixture gets a unique GraphQL body so Smart Cache's
		// validate_and_pre_save_cb (which hashes content and rejects
		// normalized duplicates) doesn't reject a second doc with the
		// same content as the first.
		static $counter = 0;
		$counter++;

		return $this->factory()->post->create( [
			'post_type'    => 'graphql_document',
			'post_status'  => 'publish',
			'post_author'  => $author,
			'post_content' => sprintf( 'query Doc%d { posts { nodes { id } } }', $counter ),
			'post_excerpt' => '',
		] );
	}

	public function test_get_returns_default_values_for_new_document() {
		wp_set_current_user( $this->admin_a );
		$id       = $this->create_doc( $this->admin_a );
		$response = $this->dispatch( 'GET', "/wp/v2/graphql_document/{$id}" );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'documentSettings', $data );
		$this->assertSame( '', $data['documentSettings']['description'] );
		$this->assertSame( [], $data['documentSettings']['aliases'] );
		$this->assertSame( '', $data['documentSettings']['maxAgeHeader'] );
		$this->assertSame( '', $data['documentSettings']['grant'] );
	}

	public function test_post_persists_description_and_grant() {
		wp_set_current_user( $this->admin_a );
		$id = $this->create_doc( $this->admin_a );

		$response = $this->dispatch(
			'POST',
			"/wp/v2/graphql_document/{$id}",
			[
				'documentSettings' => [
					'description' => 'Used by the homepage',
					'grant'       => 'allow',
				],
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'Used by the homepage', $data['documentSettings']['description'] );
		$this->assertSame( 'allow', $data['documentSettings']['grant'] );

		// Round-trip via a fresh GET to confirm persistence (not just the
		// response object's view).
		$reread = $this->dispatch( 'GET', "/wp/v2/graphql_document/{$id}" )->get_data();
		$this->assertSame( 'Used by the homepage', $reread['documentSettings']['description'] );
		$this->assertSame( 'allow', $reread['documentSettings']['grant'] );
	}

	public function test_post_persists_aliases() {
		wp_set_current_user( $this->admin_a );
		$id = $this->create_doc( $this->admin_a );

		$response = $this->dispatch(
			'POST',
			"/wp/v2/graphql_document/{$id}",
			[
				'documentSettings' => [
					'aliases' => [ 'home-feed', 'homepage' ],
				],
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertEqualsCanonicalizing(
			[ 'home-feed', 'homepage' ],
			$data['documentSettings']['aliases']
		);
	}

	public function test_post_persists_max_age_as_integer_string() {
		wp_set_current_user( $this->admin_a );
		$id = $this->create_doc( $this->admin_a );

		$response = $this->dispatch(
			'POST',
			"/wp/v2/graphql_document/{$id}",
			[
				'documentSettings' => [ 'maxAgeHeader' => 3600 ],
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( '3600', $data['documentSettings']['maxAgeHeader'] );
	}

	public function test_negative_max_age_is_rejected_as_empty() {
		wp_set_current_user( $this->admin_a );
		$id = $this->create_doc( $this->admin_a );

		$this->dispatch(
			'POST',
			"/wp/v2/graphql_document/{$id}",
			[ 'documentSettings' => [ 'maxAgeHeader' => -10 ] ]
		);

		$reread = $this->dispatch( 'GET', "/wp/v2/graphql_document/{$id}" )->get_data();
		$this->assertSame( '', $reread['documentSettings']['maxAgeHeader'] );
	}

	public function test_invalid_grant_value_is_dropped_to_empty_string() {
		wp_set_current_user( $this->admin_a );
		$id = $this->create_doc( $this->admin_a );

		$this->dispatch(
			'POST',
			"/wp/v2/graphql_document/{$id}",
			[ 'documentSettings' => [ 'grant' => 'banana' ] ]
		);

		$reread = $this->dispatch( 'GET', "/wp/v2/graphql_document/{$id}" )->get_data();
		$this->assertSame( '', $reread['documentSettings']['grant'] );
	}

	public function test_admin_b_cannot_edit_admin_a_document_settings() {
		$id = $this->create_doc( $this->admin_a );
		wp_set_current_user( $this->admin_b );

		$response = $this->dispatch(
			'POST',
			"/wp/v2/graphql_document/{$id}",
			[ 'documentSettings' => [ 'description' => 'Sneaky edit' ] ]
		);

		$this->assertSame( 403, $response->get_status() );
	}
}
