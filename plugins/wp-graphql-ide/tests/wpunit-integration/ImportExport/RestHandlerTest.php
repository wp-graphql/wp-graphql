<?php
/**
 * End-to-end coverage of the IDE's REST import/export pipeline with
 * Smart Cache active.
 *
 * The handler unit tests in `tests/wpunit/ImportExportTest.php` call
 * `ImportExport::import()` / `::export()` directly. This file proves
 * the surrounding REST layer (route registration, payload decoding,
 * response wrapping, status codes) wires up to those calls without
 * silent drift — a change to `Rest.php`'s schema or content-type
 * handling silently breaks the only path the IDE UI uses, and zero
 * unit tests fail.
 *
 * Smart Cache must be loaded for the post-type / taxonomy primitives
 * to exist; the `wpunit-integration` suite activates it.
 *
 * @package WPGraphQLIDE
 */

namespace Tests\WPGraphQLIDE\Integration\ImportExport;

class RestHandlerTest extends \Codeception\TestCase\WPTestCase {

	private $admin;
	private $rest_server;

	public function setUp(): void {
		parent::setUp();

		$this->admin = $this->factory()->user->create( [ 'role' => 'administrator' ] );

		// Fresh REST server so register_rest_route() picks up our routes —
		// without this the routes don't dispatch under WPTestCase.
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
	 * Build a JSON request against an IDE REST route. Mirrors the helper
	 * pattern in PermissionsTest.
	 */
	private function dispatch( string $method, string $path, array $body = [] ): \WP_REST_Response {
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
			return new \WP_REST_Response( $result, $status );
		}
		return $result;
	}

	private function single_collection_payload( string $tag ): array {
		return [
			'version'     => \WPGraphQLIDE\ImportExport::IMPORT_SCHEMA_VERSION,
			'collections' => [
				[
					'name'      => 'Coll-' . $tag,
					'documents' => [
						[
							'title' => 'Doc ' . $tag,
							'query' => sprintf( 'query Q%s { posts { nodes { id } } }', $tag ),
						],
					],
				],
			],
		];
	}

	// ---------------------------------------------------------------
	// POST /documents/import
	// ---------------------------------------------------------------

	public function test_import_route_decodes_json_body_and_runs_the_handler() {
		wp_set_current_user( $this->admin );
		$response = $this->dispatch(
			'POST',
			'/wpgraphql-ide/v1/documents/import',
			$this->single_collection_payload( 'rest' )
		);

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 1, $data['created'] );
		$this->assertSame( 0, $data['skipped'] );
		$this->assertCount( 1, $data['collections'] );
	}

	public function test_import_route_rejects_form_encoded_body_as_400() {
		// `get_json_params()` returns null when the content-type isn't
		// JSON. Handler then sees an empty `collections` field and 400s.
		wp_set_current_user( $this->admin );

		$request = new \WP_REST_Request( 'POST', '/wpgraphql-ide/v1/documents/import' );
		$request->set_header( 'content-type', 'application/x-www-form-urlencoded' );
		$request->set_body( 'collections=foo' );
		$result = $this->rest_server->dispatch( $request );

		// WP_Error or 400 response, both flow through invalid_payload.
		if ( $result instanceof \WP_Error ) {
			$status = $result->get_error_data();
			$this->assertSame( 400, $status['status'] ?? 500 );
		} else {
			$this->assertSame( 400, $result->get_status() );
		}
	}

	public function test_import_route_rejects_empty_collections_payload_as_400() {
		wp_set_current_user( $this->admin );
		$response = $this->dispatch( 'POST', '/wpgraphql-ide/v1/documents/import', [
			'version'     => \WPGraphQLIDE\ImportExport::IMPORT_SCHEMA_VERSION,
			'collections' => [],
		] );
		$this->assertSame( 400, $response->get_status() );
	}

	// ---------------------------------------------------------------
	// GET /documents/export
	// ---------------------------------------------------------------

	public function test_export_route_returns_the_full_handler_payload_shape() {
		wp_set_current_user( $this->admin );

		// Seed via the same REST surface the UI uses, so the response
		// we get back is a true round-trip.
		$this->dispatch(
			'POST',
			'/wpgraphql-ide/v1/documents/import',
			$this->single_collection_payload( 'export' )
		);

		$response = $this->dispatch( 'GET', '/wpgraphql-ide/v1/documents/export' );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'version', $data );
		$this->assertArrayHasKey( 'collections', $data );
		$this->assertSame(
			\WPGraphQLIDE\ImportExport::IMPORT_SCHEMA_VERSION,
			$data['version']
		);

		$names = array_column( $data['collections'], 'name' );
		$this->assertContains( 'Coll-export', $names );
	}

	public function test_round_trip_through_REST_dedups_on_re_import() {
		wp_set_current_user( $this->admin );
		$payload = $this->single_collection_payload( 'rt' );

		$first  = $this->dispatch( 'POST', '/wpgraphql-ide/v1/documents/import', $payload );
		$second = $this->dispatch( 'POST', '/wpgraphql-ide/v1/documents/import', $payload );

		$this->assertSame( 200, $first->get_status() );
		$this->assertSame( 200, $second->get_status() );

		$this->assertSame( 1, $first->get_data()['created'] );
		$this->assertSame( 0, $first->get_data()['skipped'] );

		$this->assertSame( 0, $second->get_data()['created'] );
		$this->assertSame( 1, $second->get_data()['skipped'] );
	}
}
