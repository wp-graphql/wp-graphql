<?php

$I = new FunctionalTester( $scenario );
$I->wantTo('Get public data without passing authentication headers');

$query = '
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
}';

function verifyResponse( $I ) {
	$I->seeResponseCodeIs( 200 );
	$I->seeResponseIsJson();
	$response = $I->grabResponse();
	$response_array = json_decode( $response, true );

	/**
	 * Make sure query is valid and has no errors
	 */
	$I->assertArrayNotHasKey( 'errors', $response_array  );

	/**
	 * Make sure response is properly returning data as expected
	 */
	$I->assertArrayHasKey( 'data', $response_array );

	/**
	 * Make sure there is a post returned with the data we requested
	 */
	$I->assertNotEmpty( $response_array['data']['posts']['edges'][0]['node']['id'] );
	$I->assertNotEmpty( $response_array['data']['posts']['edges'][0]['node']['title'] );
	$I->assertNotEmpty( $response_array['data']['posts']['edges'][0]['node']['link'] );
	$I->assertNotEmpty( $response_array['data']['posts']['edges'][0]['node']['date'] );	
}

/**
 * Make sure there's a post in the database to query for. If there was no data,
 * we'd have some issues.
 */
$I->havePostInDatabase([
	'post_type' => 'post',
	'post_status' => 'publish',
	'post_title' => 'test post',
	'post_content' => 'test post content'
]);

/**
 * Set the content-type so we get a proper response from the API
 */
$I->haveHttpHeader( 'Content-Type', 'application/json' );
$I->sendPOST( 'http://wpgraphql.test/graphql', json_encode( [ 'query' => $query ] ) );

verifyResponse( $I );

/**
 * Now try the same request as GET.
 */
$query_vars = http_build_query( [
	'query' => $query,
	'variables' => json_encode( [ 'foo' => 'bar ' ] ), // unused but help test variable encoding
] );

$I->haveHttpHeader( 'Content-Type', 'application/json' );
$I->sendGET( "http://wpgraphql.test/graphql?{$query_vars}" );

verifyResponse( $I );

/**
 * If the query provides an operation name, the request must provide a matching
 * operationName parameter.
 */
$query_vars = http_build_query( [
	'operationName' => 'TestQuery',
	'query' => "query TestQuery {$query}",
	'variables' => json_encode( [ 'foo' => 'bar ' ] ), // unused but help test variable encoding
] );

$I->haveHttpHeader( 'Content-Type', 'application/json' );
$I->sendGET( "http://wpgraphql.test/graphql?{$query_vars}" );

verifyResponse( $I );
