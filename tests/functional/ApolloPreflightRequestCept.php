<?php
$I = new FunctionalTester( $scenario );
$I->wantTo( 'Send a preflight Options request like Apollo and check the response' );


$I->haveHttpHeader( 'Content-Type', 'application/json' );
$I->sendOPTIONS( 'http://wpgraphql.test/graphql' );

$I->seeResponseCodeIs( 200 );

$response = $I->canSeeHttpHeader( 'Access-Control-Allow-Origin' );

$expected = $I->grabHttpHeader( 'Access-Control-Allow-Origin' );
$I->assertEquals( '*', $expected );

$expected = $I->grabHttpHeader( 'Content-Type' );
$I->assertEquals( 'application/json ; charset=UTF-8', $expected );

$access_control_allow_headers = $I->grabHttpHeader( 'Access-Control-Allow-Headers' );
$headers                      = explode( ', ', $access_control_allow_headers );

codecept_debug( $headers );

$I->assertContains( 'Content-Type', $headers );
$I->assertContains( 'Authorization', $headers );



