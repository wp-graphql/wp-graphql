<?php

/**
 * Acceptance tests for NodeByUri resolver with REST API endpoints.
 *
 * These tests verify that REST API endpoints return null instead of REST API JSON responses
 * when queried via nodeByUri. This reproduces the bug where REST API JSON was being returned
 * instead of proper GraphQL responses.
 *
 * @see https://github.com/wp-graphql/wp-graphql/issues/3513
 */
class NodeByUriRestApiCest {

	/**
	 * Set up test fixtures before each test
	 *
	 * @param AcceptanceTester $I
	 */
	public function _before( AcceptanceTester $I ) {
		// Clear authentication/session cookies to ensure deterministic unauthenticated baseline
		// This prevents other tests from affecting these tests if they log in earlier
		$I->resetCookie( 'wordpress_logged_in_' );
		$I->resetCookie( 'wordpress_sec_' );
		$I->resetCookie( 'wordpress_' );
		$I->deleteHeader( 'Cookie' );
		$I->deleteHeader( 'X-WP-Nonce' );
		$I->deleteHeader( 'Authorization' );

		// Make a simple request to ensure WordPress is fully initialized
		// This ensures rewrite rules and REST API routes are properly registered
		// Similar to how WPUnit tests flush rewrite rules in setUp()
		$I->sendGET( 'graphql', [ 'query' => '{__typename}' ] );
		$I->seeResponseCodeIs( 200 );
	}

	/**
	 * Test that nodeByUri with REST API endpoint returns proper GraphQL response (not REST API JSON)
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
	 * NOTE: This test makes an actual HTTP request to the GraphQL endpoint, which is necessary
	 * to reproduce the bug. The bug occurs when WordPress's REST API processes the request
	 * during the parse_request action, which only happens in full HTTP request contexts.
	 *
	 * @param AcceptanceTester $I
	 * @see https://github.com/wp-graphql/wp-graphql/issues/3513
	 */
	public function nodeByUriWithRestApiEndpointReturnsGraphQLResponse( AcceptanceTester $I ) {
		$I->wantTo( 'verify nodeByUri with REST API endpoint returns GraphQL response, not REST API JSON' );

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
			'graphql',
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

		// Get the raw response to inspect it
		$response = json_decode( $I->grabResponse(), true );

		// CRITICAL: Verify the response is proper GraphQL format, NOT REST API JSON
		// REST API error responses have: {"code":"rest_...","message":"...","data":{"status":400}}
		// REST API user objects have: {"id":1,"name":"...","_links":{...},"avatar_urls":{...}}
		// GraphQL responses have: {"data":{"nodeByUri":null}}

		// Check for REST API error structure at root level
		$I->assertArrayNotHasKey( 'code', $response, 'Response should not contain REST API error code at root level' );
		$I->assertArrayNotHasKey( 'message', $response, 'Response should not contain REST API error message at root level' );

		// Verify response has GraphQL structure
		$I->assertArrayHasKey( 'data', $response, 'Response should have data key (GraphQL format)' );

		// Verify nodeByUri is null (the expected behavior)
		$I->assertNull( $response['data']['nodeByUri'], 'nodeByUri should be null for REST API endpoints' );

		// Check that data.nodeByUri is not a REST API object structure
		if ( isset( $response['data']['nodeByUri'] ) && is_array( $response['data']['nodeByUri'] ) ) {
			// REST API user objects have these fields that GraphQL nodes don't
			$I->assertArrayNotHasKey( '_links', $response['data']['nodeByUri'], 'nodeByUri should not be a REST API object with _links' );
			$I->assertArrayNotHasKey( 'avatar_urls', $response['data']['nodeByUri'], 'nodeByUri should not be a REST API object with avatar_urls' );
		}

		// Verify no REST API errors leaked into GraphQL errors
		if ( isset( $response['errors'] ) ) {
			foreach ( $response['errors'] as $error ) {
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
		$response_keys = array_keys( $response );
		$valid_graphql_keys = [ 'data', 'errors', 'extensions' ];
		foreach ( $response_keys as $key ) {
			$I->assertContains( $key, $valid_graphql_keys, sprintf( 'Response key "%s" should be a valid GraphQL response key, not REST API format', $key ) );
		}
	}

	/**
	 * Test that nodeByUri with REST API endpoint returns null (not REST API JSON) - using absolute URL
	 *
	 * @param AcceptanceTester $I
	 */
	public function nodeByUriWithRestApiAbsoluteUrlReturnsGraphQLResponse( AcceptanceTester $I ) {
		$I->wantTo( 'verify nodeByUri with absolute REST API URL returns GraphQL response, not REST API JSON' );

		$uri = rest_url( 'wp/v2/users' );

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
			'graphql',
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

		$response = json_decode( $I->grabResponse(), true );

		// Verify response is GraphQL format, not REST API JSON
		$I->assertArrayNotHasKey( 'code', $response, 'Response should not contain REST API error code' );
		$I->assertArrayNotHasKey( 'message', $response, 'Response should not contain REST API error message' );
		$I->assertArrayHasKey( 'data', $response, 'Response should have data key (GraphQL format)' );
		$I->assertArrayHasKey( 'nodeByUri', $response['data'], 'Response data should have nodeByUri key' );
		$I->assertNull( $response['data']['nodeByUri'], 'nodeByUri should be null for REST API endpoints' );
	}
}
