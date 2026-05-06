<?php
/**
 * Cross-document alias uniqueness.
 *
 * Aliases on a graphql_ide_query document must not collide across documents
 * (alias names are how persisted-query clients address a query, so collisions
 * silently route to the wrong query). The REST update should reject the
 * conflicting payload with a 400.
 *
 * @package WPGraphQLIDE\DocumentSettings
 */

namespace Tests\WPGraphQLIDE\DocumentSettings;

class AliasUniquenessTest extends \Codeception\TestCase\WPTestCase {

	private $admin;
	private $rest_server;

	public function setUp(): void {
		parent::setUp();

		$this->admin = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin );

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

	private function create_doc(): int {
		return $this->factory()->post->create( [
			'post_type'    => 'graphql_ide_query',
			'post_status'  => 'publish',
			'post_author'  => $this->admin,
			'post_content' => 'query { posts { nodes { id } } }',
		] );
	}

	public function test_same_alias_on_two_documents_is_rejected() {
		$first  = $this->create_doc();
		$second = $this->create_doc();

		$ok = $this->dispatch(
			'POST',
			"/wp/v2/graphql-ide-queries/{$first}",
			[ 'documentSettings' => [ 'aliases' => [ 'home-feed' ] ] ]
		);
		$this->assertSame( 200, $ok->get_status() );

		$conflict = $this->dispatch(
			'POST',
			"/wp/v2/graphql-ide-queries/{$second}",
			[ 'documentSettings' => [ 'aliases' => [ 'home-feed' ] ] ]
		);
		$this->assertSame( 400, $conflict->get_status() );
		$this->assertSame( 'rest_invalid_param', $conflict->get_data()['code'] ?? null );
	}

	public function test_replacing_own_alias_does_not_collide_with_self() {
		$id = $this->create_doc();

		$this->dispatch(
			'POST',
			"/wp/v2/graphql-ide-queries/{$id}",
			[ 'documentSettings' => [ 'aliases' => [ 'home-feed' ] ] ]
		);

		// Submitting the same alias on the same document is a no-op, not a conflict.
		$rewrite = $this->dispatch(
			'POST',
			"/wp/v2/graphql-ide-queries/{$id}",
			[ 'documentSettings' => [ 'aliases' => [ 'home-feed', 'homepage' ] ] ]
		);

		$this->assertSame( 200, $rewrite->get_status() );
		$data = $rewrite->get_data();
		$this->assertEqualsCanonicalizing(
			[ 'home-feed', 'homepage' ],
			$data['documentSettings']['aliases']
		);
	}
}
