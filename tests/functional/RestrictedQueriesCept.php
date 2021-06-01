<?php

$I = new FunctionalTester( $scenario );
$I->wantTo( 'Test restricting the API prevents unauthorized queries' );

$options = [
	'restrict_endpoint_to_logged_in_users' => 'on'
];

$I->haveOptionInDatabase( 'graphql_general_settings', $options  );

$I->havePostInDatabase( [
	'post_type'    => 'post',
	'post_status'  => 'publish',
	'post_title'   => "test post",
	'post_content' => "test content"
] );

$I->haveHttpHeader( 'Content-Type', 'application/json' );

$I->sendPOST( 'http://localhost/graphql', json_encode( [
	'query' => '
	{
		posts{
			edges {
				node {
					id
					title
				}
			}
		}
	}'
] ) );

$I->seeResponseCodeIs( 200 );
$I->seeResponseIsJson();
$response       = $I->grabResponse();
$response_array = json_decode( $response, true );

codecept_debug( $response_array );

/**
 * Make sure response is properly returning data as expected
 */
$I->assertArrayHasKey( 'errors', $response_array, 'The API is restricted and should return an error' );

$I->assertArrayNotHasKey( 'data', $response_array, 'The API is restricted and should not return data' );

$options = [
	'restrict_endpoint_to_logged_in_users' => 'off'
];

$I->haveOptionInDatabase( 'graphql_general_settings', $options  );

$I->haveHttpHeader( 'Content-Type', 'application/json' );

$I->sendPOST( 'http://localhost/graphql', json_encode( [
	'query' => '
	{
		posts{
			edges {
				node {
					id
					title
				}
			}
		}
	}'
] ) );

$I->seeResponseCodeIs( 200 );
$I->seeResponseIsJson();
$response       = $I->grabResponse();
$response_array = json_decode( $response, true );

codecept_debug( $response_array );

/**
 * Make sure response is properly returning data as expected
 */
$I->assertArrayNotHasKey( 'errors', $response_array, 'The API is NOT restricted and should NOT return an error' );
