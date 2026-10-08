<?php
/**
 * Guards against a request the IDE sent as public running as the
 * logged-in user on a site behind HTTP Basic Auth.
 *
 * @package WPGraphQLIDE
 */

namespace Tests\WPGraphQLIDE;

use WPGraphQLIDE\Access;

class PublicRequestTest extends \Codeception\TestCase\WPTestCase {

	private $original_server;
	private $admin;

	public function setUp(): void {
		parent::setUp();
		$this->original_server = $_SERVER;
		$this->admin           = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin );
	}

	public function tearDown(): void {
		$_SERVER = $this->original_server;
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function test_guard_runs_on_graphql_http_requests(): void {
		$this->assertNotFalse(
			has_action( 'graphql_process_http_request', [ Access::class, 'force_public_request' ] )
		);
	}

	public function test_flagged_request_is_downgraded_to_guest(): void {
		$_SERVER['HTTP_X_WPGRAPHQL_IDE_PUBLIC'] = '1';

		Access::force_public_request();

		$this->assertSame( 0, get_current_user_id() );
	}

	public function test_flagged_request_is_downgraded_when_http_auth_rides_along(): void {
		// The browser attaches Basic Auth on its own; WPGraphQL reads the
		// header as non-cookie authentication and keeps the cookie user.
		$_SERVER['HTTP_AUTHORIZATION']          = 'Basic ' . base64_encode( 'staging:secret' );
		$_SERVER['HTTP_X_WPGRAPHQL_IDE_PUBLIC'] = '1';

		do_action( 'graphql_process_http_request' );

		$this->assertSame( 0, get_current_user_id() );
	}

	public function test_unflagged_request_keeps_the_current_user(): void {
		unset( $_SERVER['HTTP_X_WPGRAPHQL_IDE_PUBLIC'] );

		Access::force_public_request();

		$this->assertSame( $this->admin, get_current_user_id() );
	}

	/**
	 * The header decides who the request runs as, so a cache that keys
	 * without it could serve one viewer's response to the other.
	 */
	public function test_endpoint_varies_on_the_public_request_header(): void {
		$headers = $this->sent_headers();

		$this->assertArrayHasKey( 'Vary', $headers );

		$vary = array_map( 'trim', explode( ',', $headers['Vary'] ) );

		$this->assertContains( Access::PUBLIC_REQUEST_HEADER, $vary );

		// Core varies on its own preview header; ours is added, not swapped in.
		$this->assertContains( 'X-GraphQL-Preview', $vary );
	}

	/**
	 * A custom header missing from the allow-list fails preflight, so a
	 * cross-origin client could not send it at all.
	 */
	public function test_endpoint_accepts_the_public_request_header_cross_origin(): void {
		$headers = $this->sent_headers();

		$this->assertArrayHasKey( 'Access-Control-Allow-Headers', $headers );

		$allowed = array_map( 'trim', explode( ',', $headers['Access-Control-Allow-Headers'] ) );

		$this->assertContains( Access::PUBLIC_REQUEST_HEADER, $allowed );
	}

	public function test_vary_is_not_duplicated_when_the_filter_runs_twice(): void {
		$once  = Access::vary_on_public_request_header( [ 'Vary' => 'X-GraphQL-Preview' ] );
		$twice = Access::vary_on_public_request_header( $once );

		$this->assertSame( $once['Vary'], $twice['Vary'] );
		$this->assertSame(
			1,
			substr_count( $twice['Vary'], Access::PUBLIC_REQUEST_HEADER )
		);
	}

	public function test_allow_list_is_not_duplicated_when_the_filter_runs_twice(): void {
		$once  = Access::allow_public_request_header( [ 'Authorization' ] );
		$twice = Access::allow_public_request_header( $once );

		$this->assertSame( $once, $twice );
	}

	public function test_vary_is_set_when_no_other_header_claimed_it(): void {
		$headers = Access::vary_on_public_request_header( [] );

		$this->assertSame( Access::PUBLIC_REQUEST_HEADER, $headers['Vary'] );
	}

	/**
	 * The headers core actually sends, captured from `set_headers()` so the
	 * assertions cover the registered filters and not just the callbacks.
	 *
	 * @return array<string,string>
	 */
	private function sent_headers(): array {
		$captured = [];

		$capture = static function ( $headers ) use ( &$captured ) {
			$captured = is_array( $headers ) ? $headers : [];
		};

		add_action( 'graphql_response_set_headers', $capture );
		\WPGraphQL\Router::set_headers();
		remove_action( 'graphql_response_set_headers', $capture );

		return $captured;
	}
}
