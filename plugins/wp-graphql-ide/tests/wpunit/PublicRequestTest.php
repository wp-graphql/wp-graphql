<?php
/**
 * Tests for the IDE's "send as public visitor" guard.
 *
 * On a site behind HTTP Basic Auth the IDE can't omit credentials for
 * a public request (the web server would reject it), so it sends them
 * along with an `X-WPGraphQL-IDE-Public` header. The regression to
 * guard against: such a request executing as the logged-in user while
 * the IDE reports it as public.
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
}
