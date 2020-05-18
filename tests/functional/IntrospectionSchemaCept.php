<?php
$query = \GraphQL\Type\Introspection::getIntrospectionQuery();
$I = new FunctionalTester( $scenario );
$I->wantTo( 'Ensure Introspection query has no errors' );


// Set the content-type so we get a proper response from the API.
$I->haveHttpHeader( 'Content-Type', 'application/json' );

// Query for the menu.
$I->sendPOST( 'http://wpgraphql.test/graphql', json_encode( [
	'query' => $query
] ) );

// Check response.
$I->seeResponseCodeIs( 200 );
$I->seeResponseIsJson();
$response       = $I->grabResponse();
$response_array = json_decode( $response, true );

// The query is valid and has no errors.
$I->assertArrayNotHasKey( 'errors', $response_array );
