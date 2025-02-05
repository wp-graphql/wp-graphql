<?php

$I = new FunctionalTester($scenario);

$I->wantTo('Ensure 415 status code is returned when content-type header is missing or incorrect');

// Test with no content-type header
$I->sendPOST('http://localhost/graphql', [
    'query' => '{posts{nodes{id}}}'
]);
$I->seeResponseCodeIs(415);

// Test with incorrect content-type header
$I->haveHttpHeader('Content-Type', 'text/plain');
$I->sendPOST('http://localhost/graphql', [
    'query' => '{posts{nodes{id}}}'
]);
$I->seeResponseCodeIs(415);

// Verify that application/json content-type works correctly
$I->haveHttpHeader('Content-Type', 'application/json');
$I->sendPOST('http://localhost/graphql', [
    'query' => '{posts{nodes{id}}}'
]);
$I->seeResponseCodeIs(200);

