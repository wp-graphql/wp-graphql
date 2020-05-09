<?php

$I = new FunctionalTester( $scenario );
$I->wantTo('get proper error message on syntax error');

$query = '
{
	posts  # syntax error
		edges {
			node {
				id
				title
				link
				date
			}
		}
	}
}';


/**
 * Set the content-type so we get a proper response from the API
 */
$I->haveHttpHeader( 'Content-Type', 'application/json' );
$I->sendPOST( 'http://wpgraphql.test/graphql', json_encode( [ 'query' => $query ] ) );


/**
 * The error response 200 as the error in put in the 'errors' key
 */
$I->seeResponseCodeIs( 200 );

$I->seeResponseIsJson();
$response = $I->grabResponse();
$response_array = json_decode( $response, true );

/**
 * Make sure the errors key exists
 */
$I->assertArrayHasKey( 'errors', $response_array  );

/**
 * Assert it's proper readable syntax error array
 */
$I->assertCount( 1, $response_array['errors']  );
$I->assertEquals( 'Syntax Error: Unexpected }', $response_array['errors'][0]['message']  );
$I->assertArrayHasKey( 'locations', $response_array['errors'][0]  );
$I->assertArrayHasKey( 'extensions', $response_array['errors'][0]  );