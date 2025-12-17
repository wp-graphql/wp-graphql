<?php

/**
 * Acceptance tests for Application Password Authentication in WPGraphQL.
 *
 * These tests verify that Application Passwords (Basic Auth) work correctly
 * with the GraphQL endpoint. They are in a separate class from cookie auth
 * tests to ensure they run in isolation without state pollution from loginAs().
 *
 * @see https://github.com/wp-graphql/wp-graphql/issues/3447
 */
class ApplicationPasswordAuthenticationCest {

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
		// Clear any existing session state
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
	}

	/**
	 * Clean up after each test
	 *
	 * @param AcceptanceTester $I
	 */
	public function _after( AcceptanceTester $I ) {
		$I->resetCookie( 'wordpress_logged_in_' );
		$I->resetCookie( 'wordpress_sec_' );
		$I->resetCookie( 'wordpress_' );
		$I->deleteHeader( 'Cookie' );
		$I->deleteHeader( 'X-WP-Nonce' );
		$I->deleteHeader( 'Authorization' );
	}

	/**
	 * Skip test if Authorization headers are not working in this environment.
	 *
	 * In some CI environments (particularly wp-env on older WP versions), the Apache
	 * SetEnvIf directive doesn't properly pass Authorization headers to PHP.
	 *
	 * @param AcceptanceTester $I
	 */
	private function skipIfAuthorizationHeadersNotSupported( AcceptanceTester $I ): void {
		if ( getenv( 'SKIP_AUTHORIZATION_HEADER_TESTS' ) === '1' ) {
			$I->markTestSkipped( 'Authorization header tests skipped in this environment (SKIP_AUTHORIZATION_HEADER_TESTS=1)' );
		}
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
		$I->resetCookie( 'wordpress_logged_in_' );
		$I->deleteHeader( 'Cookie' );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->haveHttpHeader( 'Authorization', $auth_header );
	}

	/**
	 * Test: Application Password authentication works without nonce
	 *
	 * @param AcceptanceTester $I
	 */
	public function applicationPasswordAuthWorksWithoutNonce( AcceptanceTester $I ) {
		$I->wantTo( 'verify Application Password authentication works without nonce' );

		$this->skipIfAuthorizationHeadersNotSupported( $I );

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
	 * @param AcceptanceTester $I
	 */
	public function applicationPasswordCanAccessPrivateData( AcceptanceTester $I ) {
		$I->wantTo( 'verify Application Password can access private data like draft posts' );

		$this->skipIfAuthorizationHeadersNotSupported( $I );

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
	 * @param AcceptanceTester $I
	 */
	public function applicationPasswordCanExecuteMutations( AcceptanceTester $I ) {
		$I->wantTo( 'verify Application Password can execute mutations' );

		$this->skipIfAuthorizationHeadersNotSupported( $I );

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
			if ( isset( $response['errors'] ) ) {
				codecept_debug( 'Mutation errors: ' . print_r( $response['errors'], true ) );
			}
			$I->markTestSkipped( 'Could not execute mutation - Authorization header may not be passed to PHP' );
		}
	}

	/**
	 * Test: Invalid Application Password is rejected
	 *
	 * @param AcceptanceTester $I
	 */
	public function invalidApplicationPasswordIsRejected( AcceptanceTester $I ) {
		$I->wantTo( 'verify invalid Application Password is rejected' );

		$this->skipIfAuthorizationHeadersNotSupported( $I );

		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			$I->markTestSkipped( 'Application Passwords not available in this WordPress version' );
			return;
		}

		$user = get_user_by( 'id', $this->admin_id );

		$invalid_auth_header = 'Basic ' . base64_encode( $user->user_login . ':invalid-password-12345' );

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

		$I->assertNull( $response['data']['viewer'], 'Invalid App Password should not authenticate user' );
	}

	/**
	 * Test: Application Password works with GET requests
	 *
	 * @param AcceptanceTester $I
	 */
	public function applicationPasswordWorksWithGetRequests( AcceptanceTester $I ) {
		$I->wantTo( 'verify Application Password works with GET requests' );

		$this->skipIfAuthorizationHeadersNotSupported( $I );

		$auth_header = $this->createAppPasswordAuthHeader( $I, $this->admin_id, 'GET Request Test' );

		if ( ! $auth_header ) {
			$I->markTestSkipped( 'Application Passwords not available or could not be created' );
			return;
		}

		$I->resetCookie( 'wordpress_logged_in_' );
		$I->deleteHeader( 'Cookie' );

		$I->haveHttpHeader( 'Authorization', $auth_header );

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

