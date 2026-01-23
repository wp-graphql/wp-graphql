<?php

/**
 * Test that authenticated request data does not leak to public users via cache.
 *
 * This uses acceptance tests with WPBrowser to properly handle authentication
 * via the same browser session.
 *
 * The vulnerability was: WPGraphQL calls wp_set_current_user(0) mid-request,
 * causing is_user_logged_in() to return false, which incorrectly cached
 * authenticated results that could then leak to public users.
 *
 * The fix uses AppContext->viewer (set early, stable) instead of is_user_logged_in().
 */
class AuthenticatedRequestCacheCest {

	/**
	 * @var int
	 */
	protected $draft_post_id;

	/**
	 * @var string
	 */
	protected $draft_post_title;

	/**
	 * @var string
	 */
	protected $test_run_id;

	public function _before( AcceptanceTester $I ) {
		$this->test_run_id = uniqid( 'test_', true );
		$this->draft_post_title = 'Secret Draft ' . $this->test_run_id;
	}

	/**
	 * Test that draft posts visible to admin don't leak to public users via cache.
	 *
	 * This test:
	 * 1. Logs in as admin via browser
	 * 2. Makes GraphQL request for drafts via browser URL (same session)
	 * 3. Verifies admin sees the draft and response is NOT from cache
	 * 4. Makes SECOND authenticated request to verify cache is still not populated
	 * 5. Makes same request via REST module (clean session, no cookies)
	 * 6. Verifies public user does NOT see the draft
	 */
	public function testDraftContentDoesNotLeakToPublicUsers( AcceptanceTester $I ) {
		// Enable object cache
		$I->haveOptionInDatabase( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

		// Create draft post
		$this->draft_post_id = $I->havePostInDatabase( [
			'post_type'    => 'post',
			'post_status'  => 'draft',
			'post_title'   => $this->draft_post_title,
			'post_content' => 'Secret content',
		] );

		// Build unique query
		$operation_name = 'TestDraft_' . str_replace( '.', '_', $this->test_run_id );
		$query = "query {$operation_name} { posts(where: {status: DRAFT}) { nodes { title status } } }";
		$query_encoded = urlencode( $query );
		$graphql_url = "/graphql?query={$query_encoded}";

		// =====================================================
		// STEP 1: Admin logs in and makes GraphQL request
		// =====================================================
		$I->loginAsAdmin();
		$I->amOnPage( $graphql_url );

		// Grab the raw page source (JSON response)
		$auth_body = $I->grabPageSource();
		codecept_debug( 'Authenticated response: ' . $auth_body );

		$auth_response = json_decode( $auth_body, true );
		$I->assertIsArray( $auth_response, 'Response should be valid JSON' );
		$I->assertArrayHasKey( 'data', $auth_response, 'Response should have data key' );

		// Admin should see the draft post
		$auth_posts = $auth_response['data']['posts']['nodes'] ?? [];
		$found_draft = false;
		foreach ( $auth_posts as $post ) {
			if ( $post['title'] === $this->draft_post_title ) {
				$found_draft = true;
				break;
			}
		}
		$I->assertTrue( $found_draft, 'Authenticated admin should see the draft post' );

		// Verify it was NOT served from cache (first request)
		$auth_cache_info = $auth_response['extensions']['graphqlSmartCache']['graphqlObjectCache'] ?? [];
		codecept_debug( 'Auth cache info (1st request): ' . json_encode( $auth_cache_info ) );
		$I->assertEquals( [], $auth_cache_info, 'First auth request should NOT be from cache' );

		// =====================================================
		// STEP 2: Make SECOND authenticated request to verify cache is still not populated
		// =====================================================
		$I->amOnPage( $graphql_url );

		$auth_body_2 = $I->grabPageSource();
		codecept_debug( 'Second authenticated response: ' . $auth_body_2 );

		$auth_response_2 = json_decode( $auth_body_2, true );
		$I->assertIsArray( $auth_response_2, 'Second auth response should be valid JSON' );
		$I->assertArrayHasKey( 'data', $auth_response_2, 'Second auth response should have data key' );

		// Admin should still see the draft post
		$auth_posts_2 = $auth_response_2['data']['posts']['nodes'] ?? [];
		$found_draft_2 = false;
		foreach ( $auth_posts_2 as $post ) {
			if ( $post['title'] === $this->draft_post_title ) {
				$found_draft_2 = true;
				break;
			}
		}
		$I->assertTrue( $found_draft_2, 'Second authenticated request should still see the draft post' );

		// CRITICAL: Second auth request should ALSO not be from cache
		// This proves authenticated requests never populate or use the object cache
		$auth_cache_info_2 = $auth_response_2['extensions']['graphqlSmartCache']['graphqlObjectCache'] ?? [];
		codecept_debug( 'Auth cache info (2nd request): ' . json_encode( $auth_cache_info_2 ) );
		$I->assertEquals( [], $auth_cache_info_2, 'Second auth request should ALSO NOT be from cache - authenticated requests should never cache' );

		// =====================================================
		// STEP 3: Make same request as public user via REST (clean session)
		// =====================================================
		// Use sendGet from REST module - this doesn't share cookies with WPBrowser
		$I->deleteHeader( 'Authorization' );
		$I->sendGet( 'graphql', [ 'query' => $query ] );
		$I->seeResponseCodeIs( 200 );
		
		$public_body = $I->grabResponse();
		codecept_debug( 'Public response: ' . $public_body );

		$public_response = json_decode( $public_body, true );
		$I->assertIsArray( $public_response, 'Response should be valid JSON' );

		// Check cache status
		$public_cache_info = $public_response['extensions']['graphqlSmartCache']['graphqlObjectCache'] ?? [];
		codecept_debug( 'Public cache info: ' . json_encode( $public_cache_info ) );

		// CRITICAL SECURITY CHECK: Public user should NOT see draft posts
		$public_posts = $public_response['data']['posts']['nodes'] ?? [];
		codecept_debug( 'Public posts count: ' . count( $public_posts ) );
		
		foreach ( $public_posts as $post ) {
			$I->assertNotEquals(
				$this->draft_post_title,
				$post['title'],
				'SECURITY VULNERABILITY: Public user can see draft posts!'
			);
		}

		// Public request should NOT come from cache (auth didn't cache it)
		$I->assertEquals( 
			[], 
			$public_cache_info, 
			'Public request should NOT be from cache - auth request should not have cached'
		);

		// Cleanup
		$I->dontHavePostInDatabase( [ 'ID' => $this->draft_post_id ] );
		$I->dontHaveOptionInDatabase( 'graphql_cache_section' );
	}

	/**
	 * Test that public requests ARE properly cached, but authenticated requests bypass the cache.
	 *
	 * This test:
	 * 1. Makes first public request - NOT from cache (populates cache)
	 * 2. Makes second public request - SHOULD be from cache
	 * 3. Logs in as admin and makes same request - should NOT be from cache
	 */
	public function testPublicRequestsAreCached( AcceptanceTester $I ) {
		// Enable object cache
		$I->haveOptionInDatabase( 'graphql_cache_section', [ 'cache_toggle' => 'on' ] );

		$operation_name = 'TestPublic_' . str_replace( '.', '_', $this->test_run_id );
		$query_string = "query {$operation_name} { __typename }";
		$query = urlencode( $query_string );
		$graphql_url = "/graphql?query={$query}";

		// =====================================================
		// STEP 1: First public request - NOT from cache (populates cache)
		// =====================================================
		$I->amOnPage( $graphql_url );
		$body1 = $I->grabPageSource();
		codecept_debug( 'First public response: ' . $body1 );
		
		$response1 = json_decode( $body1, true );
		$I->assertIsArray( $response1, 'Response should be valid JSON' );
		
		$cache1 = $response1['extensions']['graphqlSmartCache']['graphqlObjectCache'] ?? [];
		$I->assertEquals( [], $cache1, 'First public request should NOT be from cache' );

		// =====================================================
		// STEP 2: Second public request - SHOULD be from cache
		// =====================================================
		$I->amOnPage( $graphql_url );
		$body2 = $I->grabPageSource();
		codecept_debug( 'Second public response: ' . $body2 );
		
		$response2 = json_decode( $body2, true );
		$I->assertIsArray( $response2, 'Response should be valid JSON' );
		
		$cache2 = $response2['extensions']['graphqlSmartCache']['graphqlObjectCache'] ?? [];
		$I->assertNotEmpty( $cache2, 'Second public request SHOULD be from cache' );

		// =====================================================
		// STEP 3: Authenticated request - should NOT use cache
		// Even though there's a cached entry, authenticated users bypass it entirely
		// =====================================================
		$I->loginAsAdmin();
		$I->amOnPage( $graphql_url );
		$body3 = $I->grabPageSource();
		codecept_debug( 'Authenticated response: ' . $body3 );
		
		$response3 = json_decode( $body3, true );
		$I->assertIsArray( $response3, 'Authenticated response should be valid JSON' );
		$I->assertArrayHasKey( 'data', $response3, 'Authenticated response should have data key' );
		
		$cache3 = $response3['extensions']['graphqlSmartCache']['graphqlObjectCache'] ?? [];
		$I->assertEquals( [], $cache3, 'Authenticated request should NOT be from cache - auth users bypass cache entirely' );

		// Cleanup
		$I->dontHaveOptionInDatabase( 'graphql_cache_section' );
	}
}
