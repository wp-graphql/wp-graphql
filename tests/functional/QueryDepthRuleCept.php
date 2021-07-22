<?php

$I = new FunctionalTester( $scenario );
$I->wantTo( 'Test query depth rules' );

$options = [
	'query_depth_enabled' => 'off',
	'query_depth_max' => 10
];

$I->haveOptionInDatabase( 'graphql_general_settings', $options  );

$I->havePostInDatabase( [
	'post_type'    => 'post',
	'post_status'  => 'publish',
	'post_title'   => "test post",
	'post_content' => "test content"
] );

$I->haveHttpHeader( 'Content-Type', 'application/json' );

$query = '
	{
		posts{
			edges {
				node {
					id
					title
					author {
					  node {
					    id
					    name
					    posts {
					      nodes {
					        id
					        title
					      }
					    }
					  }
					}
				}
			}
		}
	}
';

$I->sendPOST( 'http://localhost/graphql', json_encode( [
	'query' => $query
] ) );

$I->seeResponseCodeIs( 200 );
$I->seeResponseIsJson();
$response       = $I->grabResponse();
$response_array = json_decode( $response, true );

/**
 * Make sure response is properly returning data as expected
 */
$I->assertArrayNotHasKey( 'errors', $response_array, 'Query depth is disabled and the query should execute without error' );
$I->assertArrayHasKey( 'data', $response_array, 'Query depth is disabled and the query should work fine' );

$options = [
	'query_depth_enabled' => 'on',
	'query_depth_max' => 2
];

$I->haveOptionInDatabase( 'graphql_general_settings', $options  );

$I->haveHttpHeader( 'Content-Type', 'application/json' );

$I->sendPOST( 'http://localhost/graphql', json_encode( [
	'query' => $query
] ) );

$I->seeResponseCodeIs( 200 );
$I->seeResponseIsJson();
$response       = $I->grabResponse();
$response_array = json_decode( $response, true );

codecept_debug( $response_array );

/**
 * Make sure response is properly returning data as expected
 */
$I->assertArrayHasKey( 'errors', $response_array, 'Query depth is limited to 2 levels, so the query should reject with an error' );
$I->assertArrayNotHasKey( 'data', $response_array, 'Query depth is limited to 2 levels, so the query should not return data' );
