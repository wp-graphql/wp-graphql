<?php
/**
 * Tests for the IDE's capability helpers and filter propagation.
 *
 * Pre-5.0 the `wpgraphql_ide_capability_required` filter was consulted
 * in exactly one place (the menu render gate). Every other check —
 * REST permission_callbacks, post-meta auth, taxonomy capability maps,
 * the public-endpoint trimming flag — hardcoded the literal
 * `manage_graphql_ide` and bypassed the filter entirely. The net effect
 * was that a host filtering the cap to (say) `edit_others_posts` could
 * see the admin menu link but be denied at every actual operation —
 * a subtle, frustrating misconfiguration.
 *
 * These tests gate that bug:
 *
 *   1. The helpers themselves consult the filter (unit).
 *   2. A host that filters the cap to something else can actually use
 *      the IDE end-to-end against REST routes (integration). If any of
 *      the runtime checks regresses to a literal, this test goes red.
 *
 * @package WPGraphQLIDE
 */

namespace Tests\WPGraphQLIDE;

class CapabilityHelpersTest extends \Codeception\TestCase\WPTestCase {

	private $custom_cap_user;
	private $rest_server;

	public function setUp(): void {
		parent::setUp();

		// User who has a custom cap but NOT `manage_graphql_ide`.
		// Subscribers don't have `edit_others_posts` by default, so this
		// is a clean test bed for "the host changed the required cap".
		$this->custom_cap_user = $this->factory()->user->create( [ 'role' => 'subscriber' ] );
		$user                  = get_user_by( 'id', $this->custom_cap_user );
		$user->add_cap( 'use_custom_ide_cap' );

		// Fresh REST server so register_rest_route() picks up our routes.
		global $wp_rest_server;
		$wp_rest_server    = new \WP_REST_Server();
		$this->rest_server = $wp_rest_server;
		do_action( 'rest_api_init' );
	}

	public function tearDown(): void {
		remove_all_filters( 'wpgraphql_ide_capability_required' );
		global $wp_rest_server;
		$wp_rest_server = null;
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	// ---------------------------------------------------------------
	// Unit: wpgraphql_ide_get_capability()
	// ---------------------------------------------------------------

	public function test_get_capability_returns_default_when_filter_unset() {
		$this->assertSame( 'manage_graphql_ide', wpgraphql_ide_get_capability() );
	}

	public function test_get_capability_returns_filter_result() {
		add_filter(
			'wpgraphql_ide_capability_required',
			static function () {
				return 'use_custom_ide_cap';
			}
		);

		$this->assertSame( 'use_custom_ide_cap', wpgraphql_ide_get_capability() );
	}

	public function test_get_capability_falls_back_when_filter_returns_empty_string() {
		add_filter(
			'wpgraphql_ide_capability_required',
			static function () {
				return '';
			}
		);

		$this->assertSame( 'manage_graphql_ide', wpgraphql_ide_get_capability() );
	}

	public function test_get_capability_falls_back_when_filter_returns_non_string() {
		add_filter(
			'wpgraphql_ide_capability_required',
			static function () {
				return null;
			}
		);

		$this->assertSame( 'manage_graphql_ide', wpgraphql_ide_get_capability() );
	}

	// ---------------------------------------------------------------
	// Unit: wpgraphql_ide_user_can()
	// ---------------------------------------------------------------

	public function test_user_can_returns_false_for_anonymous() {
		wp_set_current_user( 0 );
		$this->assertFalse( wpgraphql_ide_user_can() );
	}

	public function test_user_can_returns_true_for_user_with_default_cap() {
		$admin = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin );

		$this->assertTrue( wpgraphql_ide_user_can() );
	}

	public function test_user_can_returns_false_for_subscriber_with_no_cap_filter() {
		wp_set_current_user( $this->custom_cap_user );

		$this->assertFalse( wpgraphql_ide_user_can() );
	}

	public function test_user_can_follows_filter_to_custom_cap() {
		// Same subscriber+custom_cap user, but now the filter declares
		// `use_custom_ide_cap` is the required cap. The user has the cap,
		// so the gate should open.
		add_filter(
			'wpgraphql_ide_capability_required',
			static function () {
				return 'use_custom_ide_cap';
			}
		);
		wp_set_current_user( $this->custom_cap_user );

		$this->assertTrue( wpgraphql_ide_user_can() );
	}

	public function test_user_can_denies_user_without_filtered_cap() {
		// Filter to a cap the subscriber doesn't have.
		add_filter(
			'wpgraphql_ide_capability_required',
			static function () {
				return 'manage_options';
			}
		);
		wp_set_current_user( $this->custom_cap_user );

		$this->assertFalse( wpgraphql_ide_user_can() );
	}

	// ---------------------------------------------------------------
	// Integration: the filter propagates to REST permission callbacks
	// ---------------------------------------------------------------

	/**
	 * The bug B1 fixes: pre-5.0 a host that filtered
	 * `wpgraphql_ide_capability_required` to a different cap would get a
	 * 403 from every REST route because the permission callbacks
	 * hardcoded `manage_graphql_ide`. Post-fix the routes must honor the
	 * filter — a user with the filtered cap but NOT the literal default
	 * is allowed through.
	 */
	public function test_filter_propagates_to_rest_permission_callbacks() {
		add_filter(
			'wpgraphql_ide_capability_required',
			static function () {
				return 'use_custom_ide_cap';
			}
		);
		wp_set_current_user( $this->custom_cap_user );

		// /wp/v2/graphql-ide-queries — the Access::enforce_rest_permissions
		// gate. Should NOT be 403 once the filter propagates. (We allow any
		// 2xx; the list may be empty for this user, which is fine.)
		$response = $this->dispatch( 'GET', '/wp/v2/graphql-ide-queries' );
		$this->assertNotSame( 403, $response->get_status(), '/wp/v2/graphql-ide-queries was 403 — the cap filter did not reach Access::enforce_rest_permissions.' );

		// /wpgraphql-ide/v1/documents/export — the Rest::register
		// permission_callback gate. Same expectation.
		$response = $this->dispatch( 'GET', '/wpgraphql-ide/v1/documents/export' );
		$this->assertNotSame( 403, $response->get_status(), '/wpgraphql-ide/v1/documents/export was 403 — the cap filter did not reach Rest::register permission_callbacks.' );
	}

	public function test_filter_denies_user_who_lacks_filtered_cap() {
		// Inverse: filter to a cap the user doesn't have. Every route
		// should 403, proving the gate is still doing its job and not
		// just open to everyone.
		add_filter(
			'wpgraphql_ide_capability_required',
			static function () {
				return 'manage_options';
			}
		);
		wp_set_current_user( $this->custom_cap_user );

		$response = $this->dispatch( 'GET', '/wp/v2/graphql-ide-queries' );
		$this->assertSame( 403, $response->get_status() );

		$response = $this->dispatch( 'GET', '/wpgraphql-ide/v1/documents/export' );
		$this->assertSame( 403, $response->get_status() );
	}

	// ---------------------------------------------------------------
	// Back-compat: the namespaced wrapper still works.
	// ---------------------------------------------------------------

	public function test_user_has_graphql_ide_capability_back_compat_wrapper() {
		// Existing internal callers (AdminUI, AssetEnqueue) use the
		// namespaced \WPGraphQLIDE\user_has_graphql_ide_capability().
		// After consolidation, that wrapper must still behave identically
		// to wpgraphql_ide_user_can().
		wp_set_current_user( $this->custom_cap_user );
		$this->assertFalse( \WPGraphQLIDE\user_has_graphql_ide_capability() );

		add_filter(
			'wpgraphql_ide_capability_required',
			static function () {
				return 'use_custom_ide_cap';
			}
		);

		$this->assertTrue( \WPGraphQLIDE\user_has_graphql_ide_capability() );
		$this->assertSame( wpgraphql_ide_user_can(), \WPGraphQLIDE\user_has_graphql_ide_capability() );
	}

	// ---------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------

	private function dispatch( string $method, string $path ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, $path );
		$result  = $this->rest_server->dispatch( $request );
		if ( $result instanceof \WP_Error ) {
			$status = $result->get_error_data();
			$status = is_array( $status ) && isset( $status['status'] )
				? (int) $status['status']
				: 500;
			return new \WP_REST_Response( $result, $status );
		}
		return $result;
	}
}
