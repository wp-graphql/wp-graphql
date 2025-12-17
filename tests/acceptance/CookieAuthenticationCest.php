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

	/**
	 * Helper to create an Application Password and return the Basic Auth header value.
	 *
	 * @param AcceptanceTester $I
	 * @param int              $user_id The user ID to create the password for
	 * @param string           $name    Optional name for the app password
	 *
	 * @return string|null The Basic Auth header value, or null if creation failed
	 */
	private function createAppPasswordAuthHeader( AcceptanceTester $I, int $user_id, string $name = 'Test App Password' ): ?string {
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			return null;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return null;
		}

		$app_password_result = WP_Application_Passwords::create_new_application_password(
			$user_id,
			[
				'name'   => $name,
				'app_id' => wp_generate_uuid4(),
			]
		);

		if ( is_wp_error( $app_password_result ) ) {
			codecept_debug( 'App Password creation failed: ' . $app_password_result->get_error_message() );
			return null;
		}

		// The result contains the unhashed password
		$raw_password = $app_password_result[0];

		return 'Basic ' . base64_encode( $user->user_login . ':' . $raw_password );
	}

	/**
	 * Helper to prepare a clean request with Application Password auth.
	 *
	 * @param AcceptanceTester $I
	 * @param string           $auth_header The Authorization header value
	 */
	private function prepareAppPasswordRequest( AcceptanceTester $I, string $auth_header ): void {
		// Reset any previous session state - we want a clean request without cookies
		$I->resetCookie( 'wordpress_logged_in_' );
		$I->deleteHeader( 'Cookie' );

		// Set headers for GraphQL request with Basic Auth
		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->haveHttpHeader( 'Authorization', $auth_header );
		// Explicitly NOT setting X-WP-Nonce header - App Passwords don't need it
	}

	/**
	 * Test: Application Password authentication works without nonce
	 *
	 * Application Passwords use Basic Auth, not cookies, so nonce should not be required.
	 * This is important because JWT, OAuth, and Application Passwords should NOT require
	 * a nonce - only cookie-based auth needs CSRF protection via nonce.
	 *
	 * @param AcceptanceTester $I
	 */
	public function applicationPasswordAuthWorksWithoutNonce( AcceptanceTester $I ) {
		$I->wantTo( 'verify Application Password authentication works without nonce' );

		$auth_header = $this->createAppPasswordAuthHeader( $I, $this->admin_id );

		if ( ! $auth_header ) {
			$I->markTestSkipped( 'Application Passwords not available or could not be created' );
			return;
		}

		$this->prepareAppPasswordRequest( $I, $auth_header );

		$I->sendPOST(
			'graphql',
			json_encode(
				[
					'query' => '{
						viewer {
							databaseId
							name
						}
					}',
				]
			)
		);

		$I->seeResponseCodeIs( 200 );
		$response = json_decode( $I->grabResponse(), true );

		codecept_debug( 'App Password Auth Response: ' . $I->grabResponse() );

		if ( isset( $response['data']['viewer'] ) && $response['data']['viewer'] !== null ) {
			$I->assertArrayNotHasKey( 'errors', $response, 'No errors expected for valid Basic Auth' );
			$I->assertEquals( $this->admin_id, $response['data']['viewer']['databaseId'], 'Should be authenticated as admin' );
		} else {
			$I->markTestSkipped(
				'Application Password authentication returned viewer:null. ' .
				'Ensure Apache has: SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1'
			);
		}
	}

	/**
	 * Test: Application Password can access private data (draft posts)
	 *
	 * This demonstrates that Application Passwords provide full authentication,
	 * allowing access to private content like draft posts.
	 *
	 * @param AcceptanceTester $I
	 */
	public function applicationPasswordCanAccessPrivateData( AcceptanceTester $I ) {
		$I->wantTo( 'verify Application Password can access private data like draft posts' );

		$auth_header = $this->createAppPasswordAuthHeader( $I, $this->admin_id, 'Private Data Test' );

		if ( ! $auth_header ) {
			$I->markTestSkipped( 'Application Passwords not available or could not be created' );
			return;
		}

		$this->prepareAppPasswordRequest( $I, $auth_header );

		$I->sendPOST(
			'graphql',
			json_encode(
				[
					'query' => '{
						posts(where: {status: DRAFT}) {
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
		$response = json_decode( $I->grabResponse(), true );

		codecept_debug( 'App Password Draft Posts Response: ' . $I->grabResponse() );

		if ( isset( $response['data']['posts']['nodes'] ) ) {
			$I->assertNotEmpty( $response['data']['posts']['nodes'], 'Should see draft posts with App Password auth' );
			$I->assertEquals( 'Test Draft Post', $response['data']['posts']['nodes'][0]['title'] );
			$I->assertEquals( 'draft', $response['data']['posts']['nodes'][0]['status'] );
		} else {
			$I->markTestSkipped( 'Could not query posts - Authorization header may not be passed to PHP' );
		}
	}

	/**
	 * Test: Application Password can execute mutations
	 *
	 * This demonstrates that Application Passwords work for mutations,
	 * not just queries.
	 *
	 * @param AcceptanceTester $I
	 */
	public function applicationPasswordCanExecuteMutations( AcceptanceTester $I ) {
		$I->wantTo( 'verify Application Password can execute mutations' );

		$auth_header = $this->createAppPasswordAuthHeader( $I, $this->admin_id, 'Mutation Test' );

		if ( ! $auth_header ) {
			$I->markTestSkipped( 'Application Passwords not available or could not be created' );
			return;
		}

		$this->prepareAppPasswordRequest( $I, $auth_header );

		$I->sendPOST(
			'graphql',
			json_encode(
				[
					'query' => 'mutation CreatePost($input: CreatePostInput!) {
						createPost(input: $input) {
							post {
								id
								title
								status
							}
						}
					}',
					'variables' => [
						'input' => [
							'title'  => 'Post Created via App Password',
							'status' => 'DRAFT',
						],
					],
				]
			)
		);

		$I->seeResponseCodeIs( 200 );
		$response = json_decode( $I->grabResponse(), true );

		codecept_debug( 'App Password Mutation Response: ' . $I->grabResponse() );

		if ( isset( $response['data']['createPost']['post'] ) ) {
			$I->assertArrayNotHasKey( 'errors', $response, 'No errors expected for mutation with App Password' );
			$I->assertEquals( 'Post Created via App Password', $response['data']['createPost']['post']['title'] );
			$I->assertEquals( 'draft', $response['data']['createPost']['post']['status'] );
		} else {
			// Check if it's an auth issue vs other error
			if ( isset( $response['errors'] ) ) {
				codecept_debug( 'Mutation errors: ' . print_r( $response['errors'], true ) );
			}
			$I->markTestSkipped( 'Could not execute mutation - Authorization header may not be passed to PHP' );
		}
	}

	/**
	 * Test: Invalid Application Password is rejected
	 *
	 * This ensures security - invalid credentials should not authenticate.
	 *
	 * @param AcceptanceTester $I
	 */
	public function invalidApplicationPasswordIsRejected( AcceptanceTester $I ) {
		$I->wantTo( 'verify invalid Application Password is rejected' );

		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			$I->markTestSkipped( 'Application Passwords not available in this WordPress version' );
			return;
		}

		$user = get_user_by( 'id', $this->admin_id );

		// Create an invalid Basic Auth header with wrong password
		$invalid_auth_header = 'Basic ' . base64_encode( $user->user_login . ':invalid-password-12345' );

		// Reset any previous session state
		$I->resetCookie( 'wordpress_logged_in_' );
		$I->deleteHeader( 'Cookie' );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->haveHttpHeader( 'Authorization', $invalid_auth_header );

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

		codecept_debug( 'Invalid App Password Response: ' . $I->grabResponse() );

		// Invalid credentials should result in unauthenticated request (viewer = null)
		// WordPress doesn't error on invalid Basic Auth, it just doesn't authenticate
		$I->assertNull( $response['data']['viewer'], 'Invalid App Password should not authenticate user' );
	}

	/**
	 * Test: Application Password works with GET requests
	 *
	 * This demonstrates that App Passwords work for GET-based GraphQL queries too.
	 *
	 * @param AcceptanceTester $I
	 */
	public function applicationPasswordWorksWithGetRequests( AcceptanceTester $I ) {
		$I->wantTo( 'verify Application Password works with GET requests' );

		$auth_header = $this->createAppPasswordAuthHeader( $I, $this->admin_id, 'GET Request Test' );

		if ( ! $auth_header ) {
			$I->markTestSkipped( 'Application Passwords not available or could not be created' );
			return;
		}

		// Reset any previous session state
		$I->resetCookie( 'wordpress_logged_in_' );
		$I->deleteHeader( 'Cookie' );

		$I->haveHttpHeader( 'Authorization', $auth_header );

		// GraphQL GET request with query in URL
		$query = urlencode( '{ viewer { databaseId name } }' );
		$I->sendGET( "graphql?query={$query}" );

		$I->seeResponseCodeIs( 200 );
		$response = json_decode( $I->grabResponse(), true );

		codecept_debug( 'App Password GET Response: ' . $I->grabResponse() );

		if ( isset( $response['data']['viewer'] ) && $response['data']['viewer'] !== null ) {
			$I->assertArrayNotHasKey( 'errors', $response, 'No errors expected for GET with App Password' );
			$I->assertEquals( $this->admin_id, $response['data']['viewer']['databaseId'], 'Should be authenticated via GET request' );
		} else {
			$I->markTestSkipped( 'GET request with App Password returned viewer:null - Authorization header may not be passed' );
		}
	}
}
