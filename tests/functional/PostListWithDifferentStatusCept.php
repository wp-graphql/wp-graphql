<?php

$I = new FunctionalTester( $scenario );
$I->wantTo( 'Get posts with different post_status' );

/**
 * Clear posts table.
 */
$I->dontHavePostInDatabase([], true);

$test_posts_statuses = ['publish', 'draft', 'future'];
foreach ($test_posts_statuses as $post_status) {
	// Add post for each type.
	$I->havePostInDatabase( [
		'post_type'    => 'post',
		'post_status'  => $post_status,
		'post_title'   => "test {$post_status} post",
		'post_content' => "test {$post_status} content"
	] );
}

/**
 * Set the content-type so we get a proper response from the API
 */
$I->haveHttpHeader( 'Content-Type', 'application/json' );

/**
 * Query for posts with all 3 statuses. Since it's a public request, we should
 * ONLY get the published post in response.
 */
$I->sendPOST( 'http://wpgraphql.test/graphql', json_encode( [
	'query' => '
	{
		posts( where: { stati: [ PUBLISH, DRAFT, FUTURE ] } ){
			edges {
				node {
				    title
					status
				}
			}
		}
	}'
] ) );

$I->seeResponseCodeIs( 200 );
$I->seeResponseIsJson();
$response       = $I->grabResponse();
$response_array = json_decode( $response, true );

/**
 * Make sure query is valid and has no errors
 */
$I->assertArrayNotHasKey( 'errors', $response_array );

/**
 * Make sure response is properly returning data as expected
 */
$I->assertArrayHasKey( 'data', $response_array );

// Only 1 posts are returned, the published post. The other posts would require authentication
$I->assertEquals( 1, count( $response_array['data']['posts']['edges'] ) );
$I->assertEquals( 'test publish post', $response_array['data']['posts']['edges'][0]['node']['title'] );
