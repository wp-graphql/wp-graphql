<?php

$I = new FunctionalTester( $scenario );
$I->wantTo( 'Test query depth rules' );

$options = [
	'query_depth_enabled' => 'off',
	'query_depth_max'     => 10,
];

$I->haveOptionInDatabase( 'graphql_general_settings', $options );

$I->havePostInDatabase(
	[
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'test post',
		'post_content' => 'test content',
	]
);

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

$I->sendPOST(
	TEST_GRAPHQL_ENDPOINT,
	json_encode(
		[
			'query' => $query,
		]
	)
);

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
	'query_depth_max'     => 2,
];

$I->haveOptionInDatabase( 'graphql_general_settings', $options );

$I->haveHttpHeader( 'Content-Type', 'application/json' );

$I->sendPOST(
	TEST_GRAPHQL_ENDPOINT,
	json_encode(
		[
			'query' => $query,
		]
	)
);

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

/**
 * Operations that only ask for introspection are not limited, but a content query
 * next to an introspection field is.
 */
$options = [
	'query_depth_enabled'          => 'on',
	'query_depth_max'              => 2,
	'public_introspection_enabled' => 'on',
];

$I->haveOptionInDatabase( 'graphql_general_settings', $options );

$introspection_query = '
	{
		__schema {
			types {
				fields {
					type {
						ofType {
							ofType {
								name
							}
						}
					}
				}
			}
		}
	}
';

$I->haveHttpHeader( 'Content-Type', 'application/json' );
$I->sendPOST( TEST_GRAPHQL_ENDPOINT, json_encode( [ 'query' => $introspection_query ] ) );
$I->seeResponseCodeIs( 200 );
$response_array = json_decode( $I->grabResponse(), true );

$I->assertArrayNotHasKey( 'errors', $response_array, 'An introspection-only query is not limited by query depth' );
$I->assertNotEmpty( $response_array['data']['__schema']['types'] );

$mixed_query = '
	{
		__type(name: "Post") {
			name
		}
		posts {
			nodes {
				author {
					node {
						posts {
							nodes {
								id
							}
						}
					}
				}
			}
		}
	}
';

$I->haveHttpHeader( 'Content-Type', 'application/json' );
$I->sendPOST( TEST_GRAPHQL_ENDPOINT, json_encode( [ 'query' => $mixed_query ] ) );
$I->seeResponseCodeIs( 200 );
$response_array = json_decode( $I->grabResponse(), true );

$I->assertArrayHasKey( 'errors', $response_array, 'A content query next to an introspection field is still limited' );
$I->assertStringContainsString( 'max query depth', $response_array['errors'][0]['message'] );
