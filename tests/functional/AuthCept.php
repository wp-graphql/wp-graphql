<?php

$I = new FunctionalTester( $scenario );
$I->wantTo('Get public data without passing authentication headers');
$I->haveHttpHeader( 'Content-Type', 'application/json' );
$I->sendPOST( 'http://wp.localhost/graphql', json_encode([
	'query' => '
	{ 
		posts { 
			edges { 
				node { 
					id
					title
					link
					date 
				} 
			} 
		} 
	}'
]) );
$I->seeResponseCodeIs( 200 );
$I->seeResponseIsJson();
$response = $I->grabResponse();
$response_array = json_decode( $response, true );
$I->assertArrayNotHasKey( 'errors', $response_array  );
$I->assertArrayHasKey( 'data', $response_array );
$I->assertNotEmpty( $response_array['data']['posts']['edges'][0]['node']['id'] );
$I->assertNotEmpty( $response_array['data']['posts']['edges'][0]['node']['title'] );
$I->assertNotEmpty( $response_array['data']['posts']['edges'][0]['node']['link'] );
$I->assertNotEmpty( $response_array['data']['posts']['edges'][0]['node']['date'] );
