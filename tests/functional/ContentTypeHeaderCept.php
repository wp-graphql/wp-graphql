<?php

$I = new FunctionalTester($scenario);

$I->wantTo('Ensure 415 status code is returned when content-type header is missing or incorrect');

// Test with no content-type header
$I->sendPOST('http://localhost/graphql', json_encode([
    'query' => '{posts{nodes{id}}}'
]));
$I->seeResponseCodeIs(415);
$I->seeResponseContainsJson([
    'errors' => [
        [
            'message' => 'HTTP POST requests must have Content-Type: application/json header. Received: '
        ]
    ]
]);

// Test with incorrect content-type header
$I->haveHttpHeader('Content-Type', 'text/plain');
$I->sendPOST('http://localhost/graphql', json_encode([
    'query' => '{posts{nodes{id}}}'
]));
$I->seeResponseCodeIs(415);
$I->seeResponseContainsJson([
    'errors' => [
        [
            'message' => 'HTTP POST requests must have Content-Type: application/json header. Received: text/plain'
        ]
    ]
]);

// Verify that application/json content-type works correctly
$I->haveHttpHeader('Content-Type', 'application/json');
$I->sendPOST('http://localhost/graphql', json_encode([
    'query' => '{posts{nodes{id}}}'
]));
$I->seeResponseCodeIs(200);

// Verify that application/json with charset works
$I->haveHttpHeader('Content-Type', 'application/json; charset=utf-8');
$I->sendPOST('http://localhost/graphql', json_encode([
    'query' => '{posts{nodes{id}}}'
]));
$I->seeResponseCodeIs(200);

