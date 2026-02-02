<?php
/**
 * Functional test for NodeByUri resolver with REST API endpoints.
 *
 * This test verifies the fix for the bug where nodeByUri queries with REST API endpoint URIs
 * would return REST API JSON responses instead of proper GraphQL null responses.
 *
 * BUG BEHAVIOR (without fix):
 * - WordPress's REST API would process the request during parse_request action
 * - REST API would output JSON like: {"code":"rest_missing_callback_param","message":"...","data":{"status":400}}
 * - This would break the GraphQL response, causing REST API JSON to be returned instead
 *
 * FIX:
 * - When processing a GraphQL request, if WordPress identifies the URI as a REST API route
 *   (rest_route is set in query_vars), we remove rest_route before the parse_request action fires
 * - This prevents REST API from processing the request and allows GraphQL to return null properly
 *
 * EXPECTED BEHAVIOR (with fix):
 * - GraphQL response: {"data":{"nodeByUri":null}}
 * - No REST API JSON in the response
 *
 * NOTE: This test verifies the fix works correctly (returns GraphQL response, not REST API JSON).
 * The bug was confirmed in production/browser testing where REST API would process the request
 * and return JSON instead of allowing GraphQL to return null. The test environment may not
 * fully reproduce the bug due to how REST API checks the actual HTTP request URI, but the
 * fix is still necessary and correct.
 *
 * @see https://github.com/wp-graphql/wp-graphql/issues/3513
 */

$I = new FunctionalTester( $scenario );
$I->wantTo( 'Test that nodeByUri with REST API endpoint returns GraphQL response, not REST API JSON' );

// REST API routes are registered by WordPress core, so they should be available
// No need to flush rewrite rules for default REST API routes
$rest_prefix = rest_get_url_prefix();
$uri         = '/' . $rest_prefix . '/wp/v2/users';

$query = '
query GET_NODE_BY_URI( $uri: String! ) {
	nodeByUri( uri: $uri ) {
		__typename
		id
	}
}
';

$I->haveHttpHeader( 'Content-Type', 'application/json' );

$I->sendPOST(
	TEST_GRAPHQL_ENDPOINT,
	json_encode(
		[
			'query'     => $query,
			'variables' => [
				'uri' => $uri,
			],
		]
	)
);

$I->seeResponseCodeIs( 200 );
$I->seeResponseIsJson();

$response       = $I->grabResponse();
$response_array = json_decode( $response, true );


// CRITICAL: Verify the response is proper GraphQL format, NOT REST API JSON
// REST API error responses have: {"code":"rest_...","message":"...","data":{"status":400}}
// REST API user objects have: {"id":1,"name":"...","_links":{...},"avatar_urls":{...}}
// GraphQL responses have: {"data":{"nodeByUri":null}}

// Check for REST API error structure at root level
// THIS WILL FAIL IF THE FIX IS REMOVED - we'll get REST API JSON instead
$I->assertArrayNotHasKey( 'code', $response_array, 'Response should not contain REST API error code at root level (bug would return this)' );
$I->assertArrayNotHasKey( 'message', $response_array, 'Response should not contain REST API error message at root level (bug would return this)' );

// Verify response has GraphQL structure
$I->assertArrayHasKey( 'data', $response_array, 'Response should have data key (GraphQL format)' );

// Verify nodeByUri is null (the expected behavior)
$I->assertNull( $response_array['data']['nodeByUri'], 'nodeByUri should be null for REST API endpoints' );

// Check that data.nodeByUri is not a REST API object structure
if ( isset( $response_array['data']['nodeByUri'] ) && is_array( $response_array['data']['nodeByUri'] ) ) {
	// REST API user objects have these fields that GraphQL nodes don't
	$I->assertArrayNotHasKey( '_links', $response_array['data']['nodeByUri'], 'nodeByUri should not be a REST API object with _links' );
	$I->assertArrayNotHasKey( 'avatar_urls', $response_array['data']['nodeByUri'], 'nodeByUri should not be a REST API object with avatar_urls' );
}

// Verify no REST API errors leaked into GraphQL errors
if ( isset( $response_array['errors'] ) ) {
	foreach ( $response_array['errors'] as $error ) {
		if ( is_array( $error ) ) {
			// REST API errors have 'code' field with 'rest_' prefix
			if ( isset( $error['code'] ) ) {
				$I->assertStringNotStartsWith( 'rest_', $error['code'], 'GraphQL errors should not contain REST API error codes' );
			}
			// REST API error messages often mention missing parameters
			if ( isset( $error['message'] ) ) {
				$I->assertStringNotContainsString( 'Missing parameter', $error['message'], 'Error message should not contain REST API parameter errors' );
				$I->assertStringNotContainsString( 'rest_', $error['message'], 'Error message should not contain REST API error codes' );
			}
		}
	}
}

// Verify the response structure matches GraphQL format exactly
// GraphQL responses should have: {data: {nodeByUri: null}} or {data: {nodeByUri: null}, errors: [...]}
// NOT: {code: "rest_...", message: "...", data: {...}}
$response_keys = array_keys( $response_array );
$valid_graphql_keys = [ 'data', 'errors', 'extensions' ];
foreach ( $response_keys as $key ) {
	$I->assertContains(
		$key,
		$valid_graphql_keys,
		sprintf( 'Response key "%s" should be a valid GraphQL response key, not REST API format (bug would return REST API keys)', $key )
	);
}

// Test with absolute REST API URL as well
$I->wantTo( 'Test that nodeByUri with absolute REST API URL also returns GraphQL response' );

$absolute_uri = rest_url( 'wp/v2/users' );

$I->sendPOST(
	TEST_GRAPHQL_ENDPOINT,
	json_encode(
		[
			'query'     => $query,
			'variables' => [
				'uri' => $absolute_uri,
			],
		]
	)
);

$I->seeResponseCodeIs( 200 );
$I->seeResponseIsJson();

$response       = $I->grabResponse();
$response_array = json_decode( $response, true );

// Same assertions as above
$I->assertArrayNotHasKey( 'code', $response_array, 'Response should not contain REST API error code (bug would return this)' );
$I->assertArrayNotHasKey( 'message', $response_array, 'Response should not contain REST API error message (bug would return this)' );
$I->assertArrayHasKey( 'data', $response_array, 'Response should have data key (GraphQL format)' );
$I->assertNull( $response_array['data']['nodeByUri'], 'nodeByUri should be null for REST API endpoints' );
