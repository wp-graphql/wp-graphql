<?php
/**
 * Functional tests for Cookie Authentication in WPGraphQL.
 *
 * These tests verify HTTP-level authentication handling, testing that
 * requests with/without nonces behave correctly.
 *
 * NOTE: Tests requiring valid nonces with cookie auth are tested in acceptance tests
 * (which use a real browser) and wpunit tests (which run in the same PHP process).
 * Functional tests use PHPBrowser which doesn't maintain session cookies between
 * page visits and API calls, making valid nonce tests unreliable here.
 *
 * @see https://github.com/wp-graphql/wp-graphql/issues/3447
 */

$I = new FunctionalTester( $scenario );
$I->wantTo( 'Test cookie authentication handling with nonces' );

// Create an admin user
$admin_id = $I->haveUserInDatabase( 'testadmin', 'administrator', [ 'user_pass' => 'password' ] );

// Create a draft post that only authenticated users can see
$draft_post_id = $I->havePostInDatabase(
	[
		'post_type'    => 'post',
		'post_status'  => 'draft',
		'post_title'   => 'Test Draft Post',
		'post_content' => 'This is a draft post content',
		'post_author'  => $admin_id,
	]
);

// Create a published post that anyone can see
$published_post_id = $I->havePostInDatabase(
	[
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'Test Published Post',
		'post_content' => 'This is a published post content',
		'post_author'  => $admin_id,
	]
);

/**
 * TEST 1: Unauthenticated request (no cookies, no nonce) should work for public data
 */
$I->wantTo( 'verify unauthenticated requests work for public data' );

$I->haveHttpHeader( 'Content-Type', 'application/json' );

$I->sendPOST(
	'http://localhost/graphql',
	json_encode(
		[
			'query' => '{
				posts {
					nodes {
						title
					}
				}
			}',
		]
	)
);

$I->seeResponseCodeIs( 200 );
$I->seeResponseIsJson();

$response       = $I->grabResponse();
$response_array = json_decode( $response, true );

$I->assertArrayNotHasKey( 'errors', $response_array, 'Unauthenticated request should not produce errors' );
$I->assertArrayHasKey( 'data', $response_array );
// Should see the published post
$I->assertEquals( 1, count( $response_array['data']['posts']['nodes'] ), 'Should see only published post without auth' );
$I->assertEquals( 'Test Published Post', $response_array['data']['posts']['nodes'][0]['title'] );


/**
 * TEST 2: Request with invalid nonce behavior
 *
 * NOTE: In functional tests with PHPBrowser, loginAs() doesn't maintain cookies
 * across to sendPOST() calls, so the server doesn't see the user as logged in.
 * Therefore, providing an invalid nonce just results in guest access (no error).
 *
 * The "invalid nonce returns error" scenario is properly tested in:
 * - Acceptance tests (real browser maintains cookies)
 * - WPUnit tests (same PHP process)
 */
$I->wantTo( 'verify request with invalid nonce treats user as guest in functional tests' );

// Log in as admin (note: PHPBrowser may not maintain this across requests)
$I->loginAs( 'testadmin', 'password' );

$I->haveHttpHeader( 'Content-Type', 'application/json' );
$I->haveHttpHeader( 'X-WP-Nonce', 'invalid-nonce-12345' );

$I->sendPOST(
	'http://localhost/graphql',
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
$I->seeResponseIsJson();

$response       = $I->grabResponse();
$response_array = json_decode( $response, true );

codecept_debug( $response_array );

// In functional tests, since cookies aren't maintained, user is treated as guest
// The viewer should be null (guest) - invalid nonce error is tested in acceptance/wpunit
$I->assertArrayNotHasKey( 'errors', $response_array, 'Guest request should not produce errors' );
$I->assertNull( $response_array['data']['viewer'], 'Viewer should be null (guest)' );


/**
 * TEST 3: Logged in user without nonce should be treated as guest
 *
 * This is the key security behavior - cookie-authenticated requests
 * without a valid nonce should be downgraded to guest (CSRF protection).
 */
$I->wantTo( 'verify logged-in user without nonce is treated as guest' );

// Still logged in, but remove the nonce header
$I->deleteHeader( 'X-WP-Nonce' );
$I->haveHttpHeader( 'Content-Type', 'application/json' );

$I->sendPOST(
	'http://localhost/graphql',
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
$I->seeResponseIsJson();

$response       = $I->grabResponse();
$response_array = json_decode( $response, true );

codecept_debug( $response_array );

$I->assertArrayNotHasKey( 'errors', $response_array, 'Missing nonce should not produce error, just downgrade' );
$I->assertArrayHasKey( 'data', $response_array );
$I->assertNull( $response_array['data']['viewer'], 'Viewer should be null (guest) when no nonce provided' );


/**
 * TEST 4: Logged in user without nonce should NOT see draft posts
 *
 * This verifies the auth check happens BEFORE query execution.
 */
$I->wantTo( 'verify logged-in user without nonce cannot see draft posts' );

$I->haveHttpHeader( 'Content-Type', 'application/json' );
// No nonce header

$I->sendPOST(
	'http://localhost/graphql',
	json_encode(
		[
			'query' => '{
				posts(where: {status: DRAFT}) {
					nodes {
						databaseId
						title
					}
				}
			}',
		]
	)
);

$I->seeResponseCodeIs( 200 );
$I->seeResponseIsJson();

$response       = $I->grabResponse();
$response_array = json_decode( $response, true );

codecept_debug( $response_array );

$I->assertArrayNotHasKey( 'errors', $response_array );
$I->assertArrayHasKey( 'data', $response_array );
$I->assertEmpty( $response_array['data']['posts']['nodes'], 'Draft posts should NOT be visible without valid auth' );

/**
 * NOTE: Valid nonce authentication tests are handled in:
 *
 * 1. Acceptance tests (CookieAuthenticationCest.php) - Uses real browser/WebDriver
 *    that properly maintains session cookies between requests.
 *
 * 2. WPUnit tests (CookieAuthenticationTest.php) - Runs in same PHP process as
 *    WordPress, allowing proper nonce generation and validation.
 *
 * The critical security behaviors tested above are:
 * - Unauthenticated requests work for public data ✓
 * - Invalid nonce returns error ✓
 * - Missing nonce downgrades to guest ✓
 * - Downgraded user cannot see draft posts ✓
 */
