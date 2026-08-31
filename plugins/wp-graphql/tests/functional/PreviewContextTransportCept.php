<?php
/**
 * Functional tests for the preview context transports over real HTTP:
 * the X-GraphQL-Preview header, the extensions.preview fallback, CORS
 * preflight advertising, cache headers, and batch semantics.
 *
 * NOTE: PHPBrowser cannot authenticate GraphQL requests (see
 * CookieAuthenticationCept), so the authorized overlay itself is covered in
 * wpunit (PreviewTest). What only this suite can prove is the transport:
 * that the header travels through the real server stack into the Request
 * (observable via the no-store cache headers, which fire whenever preview
 * context is present, authorized or not) and that unauthorized requests
 * carrying preview context stay indistinguishable from plain requests.
 */

$I = new FunctionalTester( $scenario );
$I->wantTo( 'Test the preview context transports over HTTP' );

$post_id = $I->havePostInDatabase(
	[
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'Published Title',
		'post_content' => 'Published Content',
	]
);

$query = sprintf( '{ post( id: %d, idType: DATABASE_ID ) { databaseId title isPreview } }', $post_id );

/**
 * TEST 1: CORS preflight advertises the preview header, and responses announce
 * the Vary axis.
 */
$I->wantTo( 'verify the preflight allows X-GraphQL-Preview and responses vary on it' );

$I->haveHttpHeader( 'Content-Type', 'application/json' );
$I->sendOPTIONS( TEST_GRAPHQL_ENDPOINT );
$I->seeResponseCodeIs( 200 );

$allow_headers = explode( ', ', $I->grabHttpHeader( 'Access-Control-Allow-Headers' ) );
$I->assertContains( 'X-GraphQL-Preview', $allow_headers, 'Cross-origin clients must be allowed to send the preview header' );

$vary = $I->grabHttpHeader( 'Vary' );
$I->assertStringContainsString( 'X-GraphQL-Preview', (string) $vary, 'Responses must announce the preview header as a cache axis' );

/**
 * TEST 2: An unauthenticated request carrying the header parses it through the
 * real server stack (proved by the no-store cache headers) and resolves the
 * published data with no errors, indistinguishable from a plain request.
 */
$I->wantTo( 'verify the header transport parses over HTTP and stays safe unauthenticated' );

$I->haveHttpHeader( 'Content-Type', 'application/json' );
$I->haveHttpHeader( 'X-GraphQL-Preview', sprintf( 'database_id=%d, nonce="abc123"', $post_id ) );
$I->sendPOST( TEST_GRAPHQL_ENDPOINT, json_encode( [ 'query' => $query ] ) );

$I->seeResponseCodeIs( 200 );
$I->seeResponseIsJson();

$response_array = json_decode( $I->grabResponse(), true );
$I->assertArrayNotHasKey( 'errors', $response_array, 'Preview context must never produce an error' );
$I->assertEquals( $post_id, $response_array['data']['post']['databaseId'] );
$I->assertEquals( 'Published Title', $response_array['data']['post']['title'], 'An unauthorized request gets only published data' );
$I->assertFalse( $response_array['data']['post']['isPreview'], 'An unauthorized request reports no preview state' );

$cache_control = $I->grabHttpHeader( 'Cache-Control' );
$I->assertStringContainsString( 'no-store', (string) $cache_control, 'A request carrying preview context must be no-store; this also proves the header was parsed into the Request' );

/**
 * TEST 3: A malformed header (trailing comma is an RFC 8941 parse error) is
 * discarded in its entirety: no error, no preview context (no forced no-store).
 */
$I->wantTo( 'verify a malformed header is discarded entirely per RFC 8941' );

$I->haveHttpHeader( 'Content-Type', 'application/json' );
$I->haveHttpHeader( 'X-GraphQL-Preview', sprintf( 'database_id=%d,', $post_id ) );
$I->sendPOST( TEST_GRAPHQL_ENDPOINT, json_encode( [ 'query' => $query ] ) );

$I->seeResponseCodeIs( 200 );
$response_array = json_decode( $I->grabResponse(), true );
$I->assertArrayNotHasKey( 'errors', $response_array, 'A malformed header must not produce an error' );
$I->assertEquals( 'Published Title', $response_array['data']['post']['title'] );

$cache_control = $I->grabHttpHeader( 'Cache-Control' );
$I->assertTrue(
	null === $cache_control || false === strpos( (string) $cache_control, 'no-store' ),
	'A discarded header means no preview context, so no-store must not be forced'
);

/**
 * TEST 4: The extensions.preview fallback travels in the request body.
 */
$I->wantTo( 'verify the extensions.preview fallback parses over HTTP' );

$I->deleteHeader( 'X-GraphQL-Preview' );
$I->haveHttpHeader( 'Content-Type', 'application/json' );
$I->sendPOST(
	TEST_GRAPHQL_ENDPOINT,
	json_encode(
		[
			'query'      => $query,
			'extensions' => [ 'preview' => [ 'databaseId' => $post_id ] ],
		]
	)
);

$I->seeResponseCodeIs( 200 );
$response_array = json_decode( $I->grabResponse(), true );
$I->assertArrayNotHasKey( 'errors', $response_array );
$I->assertEquals( 'Published Title', $response_array['data']['post']['title'], 'Unauthorized extensions.preview gets only published data' );

$cache_control = $I->grabHttpHeader( 'Cache-Control' );
$I->assertStringContainsString( 'no-store', (string) $cache_control, 'extensions.preview must be parsed into the Request over HTTP' );

/**
 * TEST 5: Batch requests: extensions.preview is per-operation and unsupported
 * in a batch; the operations resolve normally (published data, no errors).
 */
$I->wantTo( 'verify extensions.preview inside a batch is ignored without breaking the batch' );

$I->haveOptionInDatabase( 'graphql_general_settings', [ 'batch_queries_enabled' => 'on' ] );

$I->haveHttpHeader( 'Content-Type', 'application/json' );
$I->sendPOST(
	TEST_GRAPHQL_ENDPOINT,
	json_encode(
		[
			[
				'query'      => $query,
				'extensions' => [ 'preview' => [ 'databaseId' => $post_id ] ],
			],
			[
				'query' => $query,
			],
		]
	)
);

$I->seeResponseCodeIs( 200 );
$response_array = json_decode( $I->grabResponse(), true );

$I->assertArrayNotHasKey( 'errors', $response_array[0], 'The batch operation carrying extensions.preview must still resolve' );
$I->assertEquals( 'Published Title', $response_array[0]['data']['post']['title'] );
$I->assertArrayNotHasKey( 'errors', $response_array[1] );
$I->assertEquals( 'Published Title', $response_array[1]['data']['post']['title'] );
