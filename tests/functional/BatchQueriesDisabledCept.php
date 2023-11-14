<?php

$I = new FunctionalTester( $scenario );
$I->wantTo( 'Test batch queries return errors when batching is disabled' );

$options = [
	'batch_queries_enabled' => 'off',
];

$I->haveOptionInDatabase( 'graphql_general_settings', $options );

$settings = $I->grabOptionFromDatabase( 'graphql_general_settings' );

$I->haveHttpHeader( 'Content-Type', 'application/json' );

$I->sendPost(
	'http://localhost/graphql',
	json_encode(
		[
			[
				'query' => '{posts{nodes{id,title}}}',
			],
			[
				'query' => '{posts{nodes{id,uri}}}',
			],
		]
	)
);

$I->seeResponseCodeIs( 500 );
$I->seeResponseIsJson();
$response       = $I->grabResponse();
$response_array = json_decode( $response, true );

$I->assertSame( 'off', $settings['batch_queries_enabled'] );

$I->assertArrayHasKey( 'errors', $response_array, 'Batch Queries are NOT enabled and the first query should have errors' );
$I->assertArrayNotHasKey( 'data', $response_array, 'Batch Queries are NOT enabled and the first query should not have data' );
