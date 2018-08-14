<?php

$I = new FunctionalTester( $scenario );
$I->wantTo( 'Get all theme modification and update them' );

// $I->haveOptionInDatabase();
/**
 * Set the content-type so we get a proper response from the API
 */
$I->haveHttpHeader( 'Content-Type', 'application/json' );

/**
 * Query for theme_mods.
 */
$I->sendPOST( 'http://wpgraphql.test/graphql', json_encode( [
	'query' => '
	{
		themeMods {
      navMenuLocations(location: "primary") {
        menuId
      }
      customLogo {
        mediaItemId
      }
      background {
        mediaItemId
      }
      headerImage {
        mediaItemId
      }
      backgroundColor
      customCssPostId
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

$expected = [];

$I->assertEquals( $expected, $response_array );


/**
 * Update theme_mods.
 */
$I->sendPOST( 'http://wpgraphql.test/graphql', json_encode( [
	'query' => '
	mutation {
		updateThemeMods(input: 
			{
				clientMutationId: "someMutationId"
				backgroundColor: "FFFFFF"
				background: {
					imageId: 351
				}
				customLogo: 324,
				headerImage: {
					imageId: 350
				}
				navMenuLocations: {
				 primary: 41 
				}
			}) {
			clientMutationId
			themeMods {
				navMenuLocations(location: "primary") {
					menuId
				}
				customLogo {
					mediaItemId
				}
				background {
					mediaItemId
				}
				headerImage {
					mediaItemId
				}
				backgroundColor
				customCssPostId
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

$expected = [];

$I->assertEquals( $expected, $response_array );