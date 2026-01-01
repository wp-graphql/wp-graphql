<?php

/**
 * Acceptance tests for Cookie Authentication in WPGraphQL.
 *
 * These tests verify end-to-end authentication handling in real browser/HTTP scenarios.
 *
 * @see https://github.com/wp-graphql/wp-graphql/issues/3447
 */
class CookieAuthenticationCest {

	/**
	 * Admin user ID for tests
	 *
	 * @var int
	 */
	private $admin_id;

	/**
	 * Draft post ID for tests
	 *
	 * @var int
	 */
	private $draft_post_id;

	/**
	 * Set up test fixtures before each test
	 *
	 * @param AcceptanceTester $I
	 */
	public function _before( AcceptanceTester $I ) {
		// Clear any existing session state to prevent pollution between tests
		$I->resetCookie( 'wordpress_logged_in_' );
		$I->resetCookie( 'wordpress_sec_' );
		$I->resetCookie( 'wordpress_' );
		$I->deleteHeader( 'Cookie' );
		$I->deleteHeader( 'X-WP-Nonce' );
		$I->deleteHeader( 'Authorization' );

		// Create admin user
		$this->admin_id = $I->haveUserInDatabase( 'testadmin', 'administrator', [ 'user_pass' => 'password' ] );

		// Create a draft post
		$this->draft_post_id = $I->havePostInDatabase(
			[
				'post_type'    => 'post',
				'post_status'  => 'draft',
				'post_title'   => 'Test Draft Post',
				'post_content' => 'Draft content for testing',
				'post_author'  => $this->admin_id,
			]
		);

		// Create a published post
		$I->havePostInDatabase(
			[
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'Test Published Post',
				'post_content' => 'Published content for testing',
				'post_author'  => $this->admin_id,
			]
		);
	}

	/**
	 * Clean up after each test
	 *
	 * @param AcceptanceTester $I
	 */
	public function _after( AcceptanceTester $I ) {
		// Clear session state to prevent pollution to next test
		$I->resetCookie( 'wordpress_logged_in_' );
		$I->resetCookie( 'wordpress_sec_' );
		$I->resetCookie( 'wordpress_' );
		$I->deleteHeader( 'Cookie' );
		$I->deleteHeader( 'X-WP-Nonce' );
		$I->deleteHeader( 'Authorization' );
	}

	/**
	 * Helper to get a valid nonce from the GraphiQL IDE page.
	 *
	 * This works by logging in and visiting the GraphiQL IDE page, which outputs
	 * the nonce in a JavaScript variable (wpGraphiQLSettings.nonce).
	 *
	 * @param AcceptanceTester $I
	 * @return string The nonce value
	 */
	private function getValidNonce( AcceptanceTester $I ): string {
		// Visit the GraphiQL IDE page which outputs the nonce
		$I->amOnPage( '/wp-admin/admin.php?page=graphiql-ide' );

		// The nonce is output via wp_localize_script as: var wpGraphiQLSettings = {"nonce":"xxx",...}
		// We need to grab it from the page source
		$pageSource = $I->grabPageSource();

		// Extract the nonce from wpGraphiQLSettings JSON
		if ( preg_match( '/var\s+wpGraphiQLSettings\s*=\s*(\{[^;]+\});/', $pageSource, $matches ) ) {
			$settings = json_decode( $matches[1], true );
			if ( isset( $settings['nonce'] ) ) {
				return $settings['nonce'];
			}
		}

		// Fallback: try to find it in a different format
		if ( preg_match( '/"nonce"\s*:\s*"([^"]+)"/', $pageSource, $matches ) ) {
			return $matches[1];
		}

		throw new \Exception( 'Could not extract nonce from GraphiQL IDE page' );
	}

	/**
	 * Test: Unauthenticated request returns only public data
	 *
	 * @param AcceptanceTester $I
	 */
	public function unauthenticatedRequestReturnsPublicData( AcceptanceTester $I ) {
		$I->wantTo( 'verify unauthenticated request returns only public data' );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );

		$I->sendPOST(
			'graphql',
			json_encode(
				[
					'query' => '{
						posts {
							nodes {
								title
								status
							}
						}
					}',
				]
			)
		);

		$I->seeResponseCodeIs( 200 );
		$I->seeResponseContainsJson(
			[
				'data' => [
					'posts' => [
						'nodes' => [
							[
								'title'  => 'Test Published Post',
								'status' => 'publish',
							],
						],
					],
				],
			]
		);

		// Verify draft post is NOT in response
		$response = json_decode( $I->grabResponse(), true );
		$titles   = array_column( $response['data']['posts']['nodes'], 'title' );
		$I->assertNotContains( 'Test Draft Post', $titles, 'Draft posts should not be visible to unauthenticated users' );
	}

	/**
	 * Test: Request with valid nonce header authenticates user
	 *
	 * @param AcceptanceTester $I
	 */
	public function requestWithValidNonceHeaderAuthenticates( AcceptanceTester $I ) {
		$I->wantTo( 'verify request with valid X-WP-Nonce header authenticates user' );

		// Login as admin
		$I->loginAs( 'testadmin', 'password' );

		// Get a valid nonce from the GraphiQL IDE page
		$nonce = $this->getValidNonce( $I );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->haveHttpHeader( 'X-WP-Nonce', $nonce );

		$I->sendPOST(
			'graphql',
			json_encode(
				[
					'query' => '{
						viewer {
							databaseId
						}
					}',
				]
			)
		);

		$I->seeResponseCodeIs( 200 );
		$response = json_decode( $I->grabResponse(), true );

		$I->assertArrayNotHasKey( 'errors', $response );
		$I->assertEquals( $this->admin_id, $response['data']['viewer']['databaseId'] );
	}

	/**
	 * Test: Request with wp_rest nonce works (backward compatibility)
	 *
	 * The GraphiQL IDE uses wp_rest nonce, so this tests backward compatibility.
	 *
	 * @param AcceptanceTester $I
	 */
	public function requestWithWpRestNonceWorks( AcceptanceTester $I ) {
		$I->wantTo( 'verify request with wp_rest nonce works for backward compatibility' );

		// Login as admin
		$I->loginAs( 'testadmin', 'password' );

		// The nonce from GraphiQL IDE is a wp_rest nonce, which tests backward compat
		$nonce = $this->getValidNonce( $I );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->haveHttpHeader( 'X-WP-Nonce', $nonce );

		$I->sendPOST(
			'graphql',
			json_encode(
				[
					'query' => '{
						viewer {
							databaseId
						}
					}',
				]
			)
		);

		$I->seeResponseCodeIs( 200 );
		$response = json_decode( $I->grabResponse(), true );

		$I->assertArrayNotHasKey( 'errors', $response );
		$I->assertEquals( $this->admin_id, $response['data']['viewer']['databaseId'] );
	}

	/**
	 * Test: Logged-in user without nonce is treated as guest
	 *
	 * This is the key CSRF protection behavior.
	 *
	 * @param AcceptanceTester $I
	 */
	public function loggedInUserWithoutNonceIsTreatedAsGuest( AcceptanceTester $I ) {
		$I->wantTo( 'verify logged-in user without nonce is treated as guest' );

		// Login as admin
		$I->loginAs( 'testadmin', 'password' );

		// Make request WITHOUT nonce
		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		// Explicitly NOT setting X-WP-Nonce header

		$I->sendPOST(
			'graphql',
			json_encode(
				[
					'query' => '{
						viewer {
							databaseId
						}
					}',
				]
			)
		);

		$I->seeResponseCodeIs( 200 );
		$response = json_decode( $I->grabResponse(), true );

		// Should be treated as guest (viewer is null)
		$I->assertArrayNotHasKey( 'errors', $response, 'Missing nonce should not produce error' );
		$I->assertNull( $response['data']['viewer'], 'Viewer should be null (guest) without nonce' );
	}

	/**
	 * Test: Logged-in user without nonce cannot see draft posts
	 *
	 * This verifies auth check happens BEFORE execution.
	 *
	 * @param AcceptanceTester $I
	 */
	public function loggedInUserWithoutNonceCannotSeeDraftPosts( AcceptanceTester $I ) {
		$I->wantTo( 'verify logged-in user without nonce cannot see draft posts' );

		// Login as admin
		$I->loginAs( 'testadmin', 'password' );

		// Make request WITHOUT nonce
		$I->haveHttpHeader( 'Content-Type', 'application/json' );

		$I->sendPOST(
			'graphql',
			json_encode(
				[
					'query' => '{
						posts(where: {status: DRAFT}) {
							nodes {
								title
							}
						}
					}',
				]
			)
		);

		$I->seeResponseCodeIs( 200 );
		$response = json_decode( $I->grabResponse(), true );

		// Should NOT see draft posts
		$I->assertEmpty( $response['data']['posts']['nodes'], 'Draft posts should NOT be visible without valid nonce' );
	}

	/**
	 * Test: Logged-in user WITH nonce CAN see draft posts
	 *
	 * @param AcceptanceTester $I
	 */
	public function loggedInUserWithNonceCanSeeDraftPosts( AcceptanceTester $I ) {
		$I->wantTo( 'verify logged-in user with nonce CAN see draft posts' );

		// Login as admin
		$I->loginAs( 'testadmin', 'password' );

		// Get a valid nonce from the GraphiQL IDE page
		$nonce = $this->getValidNonce( $I );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->haveHttpHeader( 'X-WP-Nonce', $nonce );

		$I->sendPOST(
			'graphql',
			json_encode(
				[
					'query' => '{
						posts(where: {status: DRAFT}) {
							nodes {
								title
							}
						}
					}',
				]
			)
		);

		$I->seeResponseCodeIs( 200 );
		$response = json_decode( $I->grabResponse(), true );

		// SHOULD see draft posts
		$I->assertNotEmpty( $response['data']['posts']['nodes'], 'Draft posts SHOULD be visible with valid nonce' );
		$I->assertEquals( 'Test Draft Post', $response['data']['posts']['nodes'][0]['title'] );
	}

	/**
	 * Test: Invalid nonce returns error
	 *
	 * @param AcceptanceTester $I
	 */
	public function invalidNonceReturnsError( AcceptanceTester $I ) {
		$I->wantTo( 'verify invalid nonce returns error' );

		// Login as admin
		$I->loginAs( 'testadmin', 'password' );

		// Send invalid nonce
		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->haveHttpHeader( 'X-WP-Nonce', 'invalid-nonce-12345' );

		$I->sendPOST(
			'graphql',
			json_encode(
				[
					'query' => '{
						viewer {
							databaseId
						}
					}',
				]
			)
		);

		$I->seeResponseCodeIs( 403 ); // Invalid nonce returns 403 Forbidden
		$response = json_decode( $I->grabResponse(), true );

		$I->assertArrayHasKey( 'errors', $response, 'Invalid nonce should return error' );
	}

	/**
	 * Test: Authentication error returns 403 by default.
	 *
	 * The status code can be filtered to 200 for legacy clients that expect 200 with a GraphQL
	 * error response body. Use the 'graphql_authentication_error_status_code' filter in your
	 * plugin or theme to customize this behavior.
	 *
	 * @param AcceptanceTester $I
	 */
	public function authErrorStatusCodeIs403ByDefault( AcceptanceTester $I ) {
		$I->wantTo( 'verify auth error returns 403 by default (filterable via graphql_authentication_error_status_code)' );

		// Login as admin
		$I->loginAs( 'testadmin', 'password' );

		// Send invalid nonce
		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->haveHttpHeader( 'X-WP-Nonce', 'another-invalid-nonce' );

		$I->sendPOST(
			'graphql',
			json_encode(
				[
					'query' => '{
						viewer {
							databaseId
						}
					}',
				]
			)
		);

		// Default status is 403 Forbidden.
		// Use 'graphql_authentication_error_status_code' filter to change to 200 for legacy clients.
		$I->seeResponseCodeIs( 403 );
		$response = json_decode( $I->grabResponse(), true );

		$I->assertArrayHasKey( 'errors', $response, 'Should return error in response body' );
	}
}
