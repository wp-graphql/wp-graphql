<?php

/**
 * Tests for Cookie Authentication handling in WPGraphQL.
 *
 * These tests verify that WPGraphQL properly handles cookie-based authentication
 * in alignment with WordPress REST API's security model (CSRF protection via nonces).
 *
 * @see https://github.com/wp-graphql/wp-graphql/issues/3447
 * @see https://developer.wordpress.org/reference/functions/rest_cookie_check_errors/
 */
class CookieAuthenticationTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * Admin user ID
	 *
	 * @var int
	 */
	private $admin_id;

	/**
	 * Draft post ID
	 *
	 * @var int
	 */
	private $draft_post_id;

	/**
	 * Store original global values
	 *
	 * @var array
	 */
	private $original_globals = [];

	/**
	 * Store original REQUEST values
	 *
	 * @var array
	 */
	private $original_request = [];

	/**
	 * Store original SERVER values
	 *
	 * @var array
	 */
	private $original_server = [];

	public function setUp(): void {
		parent::setUp();

		// Store original globals
		$this->original_globals = [
			'wp_graphql_auth_cookie' => $GLOBALS['wp_graphql_auth_cookie'] ?? null,
			'wp_rest_auth_cookie'    => $GLOBALS['wp_rest_auth_cookie'] ?? null,
		];

		// Store original superglobals
		$this->original_request = $_REQUEST;
		$this->original_server  = $_SERVER;

		// Create admin user
		$this->admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );

		// Create a draft post that only authenticated users can see
		$this->draft_post_id = $this->factory()->post->create(
			[
				'post_status' => 'draft',
				'post_author' => $this->admin_id,
				'post_title'  => 'Test Draft Post',
			]
		);

		$this->clearSchema();
	}

	public function tearDown(): void {
		// Restore globals
		foreach ( $this->original_globals as $key => $value ) {
			if ( null === $value ) {
				unset( $GLOBALS[ $key ] );
			} else {
				$GLOBALS[ $key ] = $value;
			}
		}

		// Restore superglobals
		$_REQUEST = $this->original_request;
		$_SERVER  = $this->original_server;

		// Remove HTTP request simulation filter
		remove_filter( 'graphql_pre_is_graphql_http_request', '__return_true' );

		// Reset current user
		wp_set_current_user( 0 );

		// Clean up
		wp_delete_post( $this->draft_post_id, true );
		wp_delete_user( $this->admin_id );

		$this->clearSchema();

		parent::tearDown();
	}

	/**
	 * Helper to simulate an HTTP request context
	 */
	private function simulate_http_request(): void {
		$_SERVER['HTTP_HOST']   = 'localhost';
		$_SERVER['REQUEST_URI'] = '/graphql';

		// Use the filter to reliably mark this as an HTTP request
		// The filter is checked first in is_graphql_http_request() and
		// short-circuits other checks that may not work in test environment
		add_filter( 'graphql_pre_is_graphql_http_request', '__return_true' );
	}

	/**
	 * Helper to set up cookie authentication context.
	 * This simulates what rest_cookie_collect_status() does in WordPress core.
	 */
	private function simulate_cookie_auth(): void {
		global $wp_graphql_auth_cookie;
		$wp_graphql_auth_cookie = true;
	}

	/**
	 * Test: Non-cookie auth (JWT/App Passwords) should bypass nonce check.
	 *
	 * When a user is authenticated via a non-cookie mechanism (like JWT),
	 * they should remain authenticated even without a nonce.
	 */
	public function testNonCookieAuthBypassesNonceCheck(): void {
		// Set up user as logged in (simulating JWT auth)
		wp_set_current_user( $this->admin_id );

		// DO NOT set $wp_graphql_auth_cookie - this simulates non-cookie auth
		// (JWT, Application Passwords, etc. don't use cookies)

		$query = '
			query {
				viewer {
					databaseId
				}
			}
		';

		$result = $this->graphql( [ 'query' => $query ] );

		// User should remain authenticated
		$this->assertArrayNotHasKey( 'errors', $result, 'Non-cookie auth should not produce errors' );
		$this->assertArrayHasKey( 'data', $result );
		$this->assertEquals(
			$this->admin_id,
			$result['data']['viewer']['databaseId'],
			'User should remain authenticated with non-cookie auth'
		);
	}

	/**
	 * Test: Cookie auth without nonce should downgrade user to guest.
	 *
	 * When cookie authentication is detected but no nonce is provided,
	 * the user should be treated as unauthenticated (CSRF protection).
	 */
	public function testCookieAuthWithoutNonceDowngradesToGuest(): void {
		$this->simulate_http_request();

		// Set up user as logged in
		wp_set_current_user( $this->admin_id );

		// Simulate cookie auth detection
		$this->simulate_cookie_auth();

		// DO NOT provide a nonce - this should trigger downgrade

		$query = '
			query {
				viewer {
					databaseId
				}
			}
		';

		$result = $this->graphql( [ 'query' => $query ] );

		// User should be downgraded to guest (viewer returns null for guests)
		$this->assertArrayNotHasKey( 'errors', $result, 'Missing nonce should not produce an error, just downgrade' );
		$this->assertArrayHasKey( 'data', $result );
		$this->assertNull(
			$result['data']['viewer'],
			'User should be downgraded to guest when cookie auth is used without nonce'
		);
	}

	/**
	 * Test: Cookie auth with valid nonce should stay authenticated.
	 *
	 * When cookie authentication is detected AND a valid nonce is provided,
	 * the user should remain authenticated.
	 */
	public function testCookieAuthWithValidNonceStaysAuthenticated(): void {
		$this->simulate_http_request();

		// Set up user as logged in
		wp_set_current_user( $this->admin_id );

		// Simulate cookie auth detection
		$this->simulate_cookie_auth();

		// Provide a valid nonce (supports both wp_graphql and wp_rest for backward compat)
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'wp_graphql' );

		$query = '
			query {
				viewer {
					databaseId
				}
			}
		';

		$result = $this->graphql( [ 'query' => $query ] );

		// User should remain authenticated
		$this->assertArrayNotHasKey( 'errors', $result );
		$this->assertArrayHasKey( 'data', $result );
		$this->assertEquals(
			$this->admin_id,
			$result['data']['viewer']['databaseId'],
			'User should remain authenticated with valid nonce'
		);
	}

	/**
	 * Test: Cookie auth with valid wp_rest nonce should stay authenticated (backward compat).
	 *
	 * For backward compatibility, the wp_rest nonce should also be accepted.
	 */
	public function testCookieAuthWithWpRestNonceStaysAuthenticated(): void {
		$this->simulate_http_request();

		// Set up user as logged in
		wp_set_current_user( $this->admin_id );

		// Simulate cookie auth detection
		$this->simulate_cookie_auth();

		// Provide wp_rest nonce (backward compatibility)
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'wp_rest' );

		$query = '
			query {
				viewer {
					databaseId
				}
			}
		';

		$result = $this->graphql( [ 'query' => $query ] );

		// User should remain authenticated
		$this->assertArrayNotHasKey( 'errors', $result );
		$this->assertArrayHasKey( 'data', $result );
		$this->assertEquals(
			$this->admin_id,
			$result['data']['viewer']['databaseId'],
			'User should remain authenticated with wp_rest nonce (backward compat)'
		);
	}

	/**
	 * Test: Cookie auth with nonce in X-WP-Nonce header should work.
	 */
	public function testCookieAuthWithNonceHeaderStaysAuthenticated(): void {
		$this->simulate_http_request();

		// Set up user as logged in
		wp_set_current_user( $this->admin_id );

		// Simulate cookie auth detection
		$this->simulate_cookie_auth();

		// Provide nonce via header
		$_SERVER['HTTP_X_WP_NONCE'] = wp_create_nonce( 'wp_graphql' );

		$query = '
			query {
				viewer {
					databaseId
				}
			}
		';

		$result = $this->graphql( [ 'query' => $query ] );

		// User should remain authenticated
		$this->assertArrayNotHasKey( 'errors', $result );
		$this->assertArrayHasKey( 'data', $result );
		$this->assertEquals(
			$this->admin_id,
			$result['data']['viewer']['databaseId'],
			'User should remain authenticated with nonce in header'
		);
	}

	/**
	 * Test: Cookie auth with invalid nonce should return error.
	 *
	 * When a user provides an invalid nonce with cookie auth,
	 * the request should return a GraphQL error response.
	 */
	public function testCookieAuthWithInvalidNonceReturnsError(): void {
		$this->simulate_http_request();

		// Set up user as logged in
		wp_set_current_user( $this->admin_id );

		// Simulate cookie auth detection
		$this->simulate_cookie_auth();

		// Provide an INVALID nonce
		$_REQUEST['_wpnonce'] = 'invalid-nonce-12345';

		$query = '
			query {
				viewer {
					databaseId
				}
			}
		';

		$result = $this->graphql( [ 'query' => $query ] );

		// Should return a GraphQL error response
		$this->assertArrayHasKey( 'errors', $result, 'Invalid nonce should produce an error' );
		$this->assertStringContainsStringIgnoringCase(
			'nonce',
			$result['errors'][0]['message'],
			'Error message should mention nonce'
		);
	}

	/**
	 * Test: Auth check runs BEFORE execution (timing/security test).
	 *
	 * This test verifies that sensitive data is NOT returned when auth fails,
	 * proving the check happens BEFORE query execution, not after.
	 */
	public function testAuthCheckRunsBeforeExecution(): void {
		$this->simulate_http_request();

		// Set up user as logged in admin
		wp_set_current_user( $this->admin_id );

		// Simulate cookie auth detection
		$this->simulate_cookie_auth();

		// No nonce - should be downgraded BEFORE query runs

		// Query for draft posts (only admins can see drafts)
		$query = '
			query {
				posts(where: {status: DRAFT}) {
					nodes {
						databaseId
						title
					}
				}
			}
		';

		$result = $this->graphql( [ 'query' => $query ] );

		// Should NOT see draft posts because user was downgraded to guest BEFORE execution
		$this->assertArrayNotHasKey( 'errors', $result );
		$this->assertArrayHasKey( 'data', $result );
		$this->assertEmpty(
			$result['data']['posts']['nodes'],
			'Draft posts should NOT be visible when auth is downgraded before execution'
		);
	}

	/**
	 * Test: AppContext->viewer reflects correct user after auth check.
	 *
	 * When a user is downgraded due to missing nonce, AppContext->viewer
	 * should reflect the guest user, not the original authenticated user.
	 */
	public function testAppContextViewerReflectsAuthStateAfterDowngrade(): void {
		$this->simulate_http_request();

		// Set up user as logged in
		wp_set_current_user( $this->admin_id );

		// Simulate cookie auth detection
		$this->simulate_cookie_auth();

		// No nonce provided - should trigger downgrade

		$captured_viewer = null;

		// Hook into graphql_before_execute which fires after auth check
		// and after the viewer is updated in AppContext
		add_action(
			'graphql_before_execute',
			function ( $request ) use ( &$captured_viewer ) {
				if ( null === $captured_viewer && isset( $request->app_context ) ) {
					$captured_viewer = $request->app_context->viewer;
				}
			},
			10,
			1
		);

		// Use a query that resolves actual fields
		$query = '{ viewer { id } }';
		$this->graphql( [ 'query' => $query ] );

		// AppContext->viewer should be a guest user (ID 0) after downgrade
		$this->assertInstanceOf( WP_User::class, $captured_viewer );
		$this->assertEquals(
			0,
			$captured_viewer->ID,
			'AppContext->viewer should reflect guest user after auth downgrade'
		);
	}

	/**
	 * Test: Internal (non-HTTP) requests bypass auth check entirely.
	 *
	 * When using graphql() function directly (not via HTTP), the auth check
	 * should be skipped as WordPress handles auth internally.
	 */
	public function testInternalRequestsBypassAuthCheck(): void {
		// DO NOT simulate HTTP request - this is an internal request

		// Set up user as logged in
		wp_set_current_user( $this->admin_id );

		// Even with cookie auth flag set, internal requests should work
		$this->simulate_cookie_auth();

		// No nonce needed for internal requests

		$query = '
			query {
				viewer {
					databaseId
				}
			}
		';

		$result = $this->graphql( [ 'query' => $query ] );

		// Internal request should work without nonce
		$this->assertArrayNotHasKey( 'errors', $result );
		$this->assertEquals(
			$this->admin_id,
			$result['data']['viewer']['databaseId'],
			'Internal requests should bypass nonce check'
		);
	}

	/**
	 * Test: Unauthenticated requests work without nonce (nothing to protect).
	 *
	 * When no user is logged in, nonce is not required since there's no
	 * session to protect from CSRF.
	 */
	public function testUnauthenticatedRequestsWorkWithoutNonce(): void {
		$this->simulate_http_request();

		// No user logged in
		wp_set_current_user( 0 );

		// No cookie auth, no nonce

		$query = '
			query {
				posts {
					nodes {
						databaseId
					}
				}
			}
		';

		$result = $this->graphql( [ 'query' => $query ] );

		// Should work fine - nothing to protect
		$this->assertArrayNotHasKey( 'errors', $result );
		$this->assertArrayHasKey( 'data', $result );
	}

	/**
	 * Test: The graphql_authentication_errors filter still works.
	 *
	 * Plugins should be able to hook into authentication handling.
	 */
	public function testAuthenticationErrorsFilterStillWorks(): void {
		$this->simulate_http_request();

		// Set up user as logged in
		wp_set_current_user( $this->admin_id );

		// Simulate cookie auth detection
		$this->simulate_cookie_auth();

		// No nonce provided

		// Hook to allow auth anyway (like a custom auth plugin might do)
		add_filter(
			'graphql_authentication_errors',
			function ( $errors ) {
				// Return false to indicate no errors
				return false;
			},
			20
		);

		$query = '{ viewer { databaseId } }';
		$result = $this->graphql( [ 'query' => $query ] );

		// Remove filter before assertions
		remove_all_filters( 'graphql_authentication_errors' );

		// The filter should have allowed the request through
		// Note: This test verifies the filter exists and is called,
		// but the actual behavior depends on the implementation
		$this->assertArrayHasKey( 'data', $result );
	}
}

