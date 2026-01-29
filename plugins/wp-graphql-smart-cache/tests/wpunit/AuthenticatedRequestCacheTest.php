<?php

namespace WPGraphQL\SmartCache;

use WPGraphQL\SmartCache\Cache\Results;

/**
 * Test that authenticated requests are handled correctly by the object cache.
 *
 * ## Background
 *
 * This tests the caching behavior for authenticated vs unauthenticated requests.
 * The object cache should only store results from unauthenticated requests to ensure
 * that authenticated user data is not inadvertently served to public users.
 *
 * ## Request Execution Order in WPGraphQL\Request
 *
 * 1. Request is created, AppContext->viewer is set to current user (e.g., admin with ID 123)
 * 2. before_execute() runs - stores globals, handles batch setup (NO auth checks here!)
 * 3. Query executes, returning data based on admin's permissions (e.g., draft posts)
 * 4. after_execute() is called
 * 5. has_authentication_errors() is called which does:
 *    - Checks if nonce is present
 *    - If NO nonce: calls wp_set_current_user(0) to treat as unauthenticated
 *    - This has been the behavior since 2019 (see Request.php lines 355-361)
 * 6. after_execute_actions() runs (lines 420-427)
 * 7. 'graphql_return_response' action fires - THIS IS WHERE SMART CACHE SAVES TO CACHE
 *
 * ## The Challenge
 *
 * At step 7, if we check is_user_logged_in(), it returns FALSE because wp_set_current_user(0)
 * was called in step 5. But the query results from step 3 contain authenticated data!
 *
 * So we would:
 * - See is_user_logged_in() === false
 * - Think "this is an unauthenticated request, safe to cache"
 * - Cache authenticated data (draft posts, private content, etc.)
 * - Public users then get this cached authenticated data
 *
 * ## Historical Context
 *
 * The comment in WPGraphQL core at line 408-409 says "prevent execution" but
 * has_authentication_errors() runs in after_execute() - AFTER the query has already executed!
 *
 * GitHub Issue #38 (wp-graphql-jwt-authentication, July 2019)
 * discusses this exact problem - authentication errors should halt execution BEFORE
 * the query runs, not after. The issue suggests using the rest_authentication_errors
 * hook pattern to abort processing early. While the issue is closed it seems it might not be fully addressed.
 *
 * @see https://github.com/wp-graphql/wp-graphql-jwt-authentication/issues/38
 *
 * ## The Solution
 *
 * Instead of checking is_user_logged_in() (which changes mid-request), we check
 * AppContext->viewer which is set once at Request creation and never changes.
 *
 * - AppContext->viewer is set in Request constructor via WPGraphQL::get_app_context()
 * - This captures the REAL user at request start, before any nonce checks
 * - Even after wp_set_current_user(0) is called, AppContext->viewer still reflects
 *   the original authenticated user
 *
 * Additionally, we cache the result of is_object_cache_enabled() per-request to ensure
 * consistent behavior throughout the entire request lifecycle.
 *
 * @see vendor/wp-graphql/wp-graphql/src/Request.php lines 355-361 (wp_set_current_user(0))
 * @see vendor/wp-graphql/wp-graphql/src/Request.php lines 408-427 (execution order)
 * @see vendor/wp-graphql/wp-graphql/src/WPGraphQL.php line 950 (AppContext->viewer set)
 */
class AuthenticatedRequestCacheTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * @var \WP_User
	 */
	protected $admin_user;

	/**
	 * @var int
	 */
	protected $draft_post_id;

	/**
	 * @var int
	 */
	protected $published_post_id;

	public function setUp(): void {
		parent::setUp();

		// Enable object caching
		add_option( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

		// Create an admin user
		$this->admin_user = self::factory()->user->create_and_get( [
			'role' => 'administrator',
		] );

		// Create a draft post (only visible to authenticated users with proper capabilities)
		$this->draft_post_id = self::factory()->post->create( [
			'post_type'   => 'post',
			'post_status' => 'draft',
			'post_title'  => 'Secret Draft Post',
			'post_author' => $this->admin_user->ID,
		] );

		// Create a published post
		$this->published_post_id = self::factory()->post->create( [
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'Public Published Post',
		] );
	}

	public function tearDown(): void {
		delete_option( 'graphql_cache_section' );
		wp_delete_post( $this->draft_post_id, true );
		wp_delete_post( $this->published_post_id, true );
		wp_delete_user( $this->admin_user->ID );

		parent::tearDown();
	}

	/**
	 * Test that is_object_cache_enabled returns false when AppContext viewer exists (authenticated),
	 * even if wp_set_current_user(0) was called later.
	 *
	 * This is the core of the fix - using AppContext->viewer instead of
	 * is_user_logged_in() which can change mid-request.
	 */
	public function testCacheIsDisabledWhenAppContextViewerExists() {
		// Log in as admin before creating the GraphQL request
		wp_set_current_user( $this->admin_user->ID );

		// Execute a simple query - this creates a Request with AppContext->viewer set to admin
		$query = '{ __typename }';
		$response = graphql( [ 'query' => $query ] );

		// The cache should have been disabled for this authenticated request
		$this->assertEmpty(
			$response['extensions']['graphqlSmartCache']['graphqlObjectCache'] ?? [],
			'Authenticated request should not be cached'
		);
	}

	/**
	 * Test that cache IS enabled for truly unauthenticated requests.
	 */
	public function testCacheIsEnabledForUnauthenticatedRequests() {
		// Ensure no user is logged in
		wp_set_current_user( 0 );

		// Execute a simple query twice
		$query = '{ __typename }';

		// First request - should execute and cache
		$response1 = graphql( [ 'query' => $query ] );
		$this->assertArrayHasKey( 'data', $response1 );

		// Second request - should be served from cache
		$response2 = graphql( [ 'query' => $query ] );

		// The second response should indicate it came from cache
		$this->assertNotEmpty(
			$response2['extensions']['graphqlSmartCache']['graphqlObjectCache'] ?? [],
			'Second unauthenticated request should be served from cache'
		);
	}

	/**
	 * Test the real-world scenario with draft posts.
	 *
	 * This test replicates the scenario:
	 *
	 * 1. Admin user is logged in (authenticated via WordPress session/cookie)
	 * 2. Admin makes request: /graphql/?query={posts(where:{status:DRAFT}){nodes{title status}}}
	 * 3. Admin sees draft posts in response (expected - they have permission)
	 * 4. WPGraphQL core calls wp_set_current_user(0) because no nonce was provided
	 * 5. Smart cache attempts to save results - should be BLOCKED because user WAS authenticated
	 * 6. Public user makes the same request
	 * 7. Public user should NOT see the draft posts
	 *
	 * WITHOUT THE FIX: At step 5, is_user_logged_in() returns FALSE (because of step 4),
	 * so the cache thinks it's safe to save, and public users get cached draft posts.
	 *
	 * WITH THE FIX: At step 5, we check AppContext->viewer which still reflects the admin
	 * user from step 1, so we correctly block caching.
	 */
	public function testDraftPostsDoNotLeakFromAuthenticatedToPublicUsers() {
		// =====================================================================
		// STEP 1: Admin user is logged in
		// =====================================================================
		wp_set_current_user( $this->admin_user->ID );

		// This is the exact query from the real-world scenario
		// /graphql/?query={posts(where:{status:DRAFT}){nodes{title status}}}
		$query = '{
			posts(where: {status: DRAFT}) {
				nodes {
					title
					status
				}
			}
		}';

		// =====================================================================
		// STEP 2-3: Admin executes query and sees draft posts
		// =====================================================================
		$admin_response = graphql( [ 'query' => $query ] );

		// Verify admin can see the draft post
		$this->assertArrayHasKey( 'data', $admin_response );
		$this->assertArrayHasKey( 'posts', $admin_response['data'] );

		$admin_posts = $admin_response['data']['posts']['nodes'];
		$found_draft = false;
		foreach ( $admin_posts as $post ) {
			if ( 'Secret Draft Post' === $post['title'] ) {
				$found_draft = true;
				// GraphQL returns status in lowercase
				$this->assertEquals( 'draft', strtolower( $post['status'] ) );
			}
		}
		$this->assertTrue( $found_draft, 'Admin should see the draft post' );

		// =====================================================================
		// STEP 4-5: Verify the response was NOT cached
		// (This is where the fix prevents the issue)
		// =====================================================================
		$this->assertEmpty(
			$admin_response['extensions']['graphqlSmartCache']['graphqlObjectCache'] ?? [],
			'Admin request should NOT be served from cache'
		);

		// =====================================================================
		// STEP 6: Public user (incognito window) makes the same request
		// =====================================================================
		wp_set_current_user( 0 ); // Simulate unauthenticated user

		$public_response = graphql( [ 'query' => $query ] );

		// =====================================================================
		// STEP 7: Verify public user does NOT see draft posts
		// =====================================================================
		$this->assertArrayHasKey( 'data', $public_response );
		$public_posts = $public_response['data']['posts']['nodes'] ?? [];

		// Public user should NOT see the draft post - this is the critical check
		foreach ( $public_posts as $post ) {
			$this->assertNotEquals(
				'Secret Draft Post',
				$post['title'],
				'Public user should not see draft post that was visible to admin.'
			);
			$this->assertNotEquals(
				'draft',
				strtolower( $post['status'] ),
				'Public user should not see draft content.'
			);
		}

		// Also verify the public response was NOT served from a polluted cache
		$this->assertEmpty(
			$public_response['extensions']['graphqlSmartCache']['graphqlObjectCache'] ?? [],
			'Public request should not be served from a cache that was potentially populated by authenticated user'
		);
	}

	/**
	 * Test that authenticated query results are NOT cached and don't leak to public users.
	 *
	 * This simulates the full flow:
	 * 1. Admin user is logged in
	 * 2. Request is created with AppContext->viewer set to admin
	 * 3. Query executes and returns draft posts
	 * 4. Cache save is attempted but should be blocked
	 * 5. Public user makes same query
	 * 6. Verify: Public user should NOT see cached authenticated data
	 */
	public function testAuthenticatedQueryResultsAreNotCached() {
		// Log in as admin
		wp_set_current_user( $this->admin_user->ID );

		$query = '
		query GetDraftPosts {
			posts(where: {status: DRAFT}) {
				nodes {
					id
					title
					status
				}
			}
		}
		';

		// Execute the query as authenticated user
		$response = graphql( [ 'query' => $query ] );

		// Verify we got the draft post in the response
		$this->assertArrayHasKey( 'data', $response );
		$this->assertArrayHasKey( 'posts', $response['data'] );

		// Now check that the result was NOT cached
		$this->assertEmpty(
			$response['extensions']['graphqlSmartCache']['graphqlObjectCache'] ?? [],
			'Authenticated request should not be from cache'
		);

		// Now log out and make the same request
		wp_set_current_user( 0 );

		$public_response = graphql( [ 'query' => $query ] );

		// The public response should also NOT be from cache (because the authenticated
		// user's response should not have been cached in the first place)
		$this->assertEmpty(
			$public_response['extensions']['graphqlSmartCache']['graphqlObjectCache'] ?? [],
			'Public request should not be served from a cache that was populated by authenticated user'
		);

		// And the public response should NOT contain the draft post
		$public_posts = $public_response['data']['posts']['nodes'] ?? [];
		foreach ( $public_posts as $post ) {
			$this->assertNotEquals(
				'Secret Draft Post',
				$post['title'],
				'Public user should NOT see draft posts that were visible to authenticated user'
			);
		}
	}

	/**
	 * Test that the Cache-Control header method correctly identifies authenticated requests.
	 *
	 * This ensures that network caches (Varnish, CDN) also don't cache authenticated responses.
	 * The Cache-Control: no-store header tells upstream caches not to store the response.
	 *
	 * Note: The graphql_response_headers_to_send filter only fires for HTTP requests,
	 * not internal graphql() calls. So we test the method directly.
	 */
	public function testCacheControlHeaderSetForAuthenticatedRequests() {
		// Log in as admin
		wp_set_current_user( $this->admin_user->ID );

		// Execute a query to set up the request on the Results instance
		$query = '{ __typename }';
		graphql( [ 'query' => $query ] );

		// Get the Results instance and test the header method directly
		$results = new Results();
		$results->init();

		// We need to set the request on the Results instance
		// Use reflection to set the request property
		$reflection = new \ReflectionClass( $results );
		$request_property = $reflection->getProperty( 'request' );
		$request_property->setAccessible( true );

		// Create a mock request with an authenticated viewer
		$mock_request = new \stdClass();
		$mock_request->app_context = new \stdClass();
		$mock_request->app_context->viewer = wp_get_current_user();

		$request_property->setValue( $results, $mock_request );

		// Test the header filter method directly
		$headers = [ 'Content-Type' => 'application/json' ];
		$filtered_headers = $results->add_no_cache_headers_for_authenticated_requests( $headers );

		$this->assertArrayHasKey( 'Cache-Control', $filtered_headers, 'Cache-Control header should be set for authenticated requests' );
		$this->assertEquals( 'no-store', $filtered_headers['Cache-Control'], 'Cache-Control should be no-store for authenticated requests' );
	}

	/**
	 * Test that Cache-Control header is NOT set to no-store for unauthenticated requests.
	 *
	 * Unauthenticated requests CAN be cached by network caches, so we should not set
	 * Cache-Control: no-store for them.
	 */
	public function testCacheControlHeaderNotSetForUnauthenticatedRequests() {
		// Ensure no user is logged in
		wp_set_current_user( 0 );

		// Get the Results instance and test the header method directly
		$results = new Results();
		$results->init();

		// Use reflection to set the request property
		$reflection = new \ReflectionClass( $results );
		$request_property = $reflection->getProperty( 'request' );
		$request_property->setAccessible( true );

		// Create a mock request with an unauthenticated viewer
		$mock_request = new \stdClass();
		$mock_request->app_context = new \stdClass();
		$mock_request->app_context->viewer = wp_get_current_user(); // Returns user with ID 0

		$request_property->setValue( $results, $mock_request );

		// Test the header filter method directly
		$headers = [ 'Content-Type' => 'application/json' ];
		$filtered_headers = $results->add_no_cache_headers_for_authenticated_requests( $headers );

		// Cache-Control should NOT be set to no-store
		$this->assertNotEquals(
			'no-store',
			$filtered_headers['Cache-Control'] ?? '',
			'Cache-Control should not be no-store for unauthenticated requests'
		);
	}

	/**
	 * Test that the is_object_cache_enabled result is cached after first determination.
	 *
	 * This ensures consistent behavior throughout the request even if something
	 * tries to change the auth state mid-request.
	 *
	 * The property is reset at the start of each new request (in get_query_results_from_cache_cb)
	 * to ensure each request gets a fresh evaluation based on its own auth state.
	 */
	public function testIsObjectCacheEnabledResultIsCached() {
		$results = new Results();
		$results->init();

		// Ensure no user is logged in
		wp_set_current_user( 0 );

		// We need to set a mock request on the Results object
		// Since we can't easily create a full Request object, we'll test via graphql()
		$query = '{ __typename }';

		// First request as unauthenticated - should enable cache
		$response1 = graphql( [ 'query' => $query ] );

		// Second identical request should come from cache
		$response2 = graphql( [ 'query' => $query ] );

		$this->assertNotEmpty(
			$response2['extensions']['graphqlSmartCache']['graphqlObjectCache'] ?? [],
			'Second request should be served from cache, proving cache is enabled'
		);
	}

	/**
	 * Test that multiple sequential requests with different auth states are handled correctly.
	 *
	 * This ensures the per-request reset of is_object_cache_enabled works correctly.
	 */
	public function testSequentialRequestsWithDifferentAuthStates() {
		$query = '{ __typename }';

		// Request 1: Unauthenticated - should cache
		wp_set_current_user( 0 );
		$response1 = graphql( [ 'query' => $query ] );
		$this->assertArrayHasKey( 'data', $response1 );

		// Request 2: Unauthenticated again - should be from cache
		$response2 = graphql( [ 'query' => $query ] );
		$this->assertNotEmpty(
			$response2['extensions']['graphqlSmartCache']['graphqlObjectCache'] ?? [],
			'Second unauthenticated request should be from cache'
		);

		// Request 3: Authenticated - should NOT use cache and NOT cache result
		wp_set_current_user( $this->admin_user->ID );
		$response3 = graphql( [ 'query' => $query ] );
		$this->assertEmpty(
			$response3['extensions']['graphqlSmartCache']['graphqlObjectCache'] ?? [],
			'Authenticated request should not be from cache'
		);

		// Request 4: Back to unauthenticated - cache should still work
		// (the authenticated request should not have polluted the cache)
		wp_set_current_user( 0 );
		$response4 = graphql( [ 'query' => $query ] );
		$this->assertNotEmpty(
			$response4['extensions']['graphqlSmartCache']['graphqlObjectCache'] ?? [],
			'Unauthenticated request after authenticated request should still use cache'
		);
	}

}
