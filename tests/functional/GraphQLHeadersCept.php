<?php

$I = new FunctionalTester( $scenario );
$I->wantTo( 'Test GraphQL Keys returned in headers' );

// First test with Query_analyzer disabled
$graphql_general_settings                           = get_option( 'graphql_general_settings' );
$graphql_general_settings['query_analyzer_enabled'] = 'off';
update_option( 'graphql_general_settings', $graphql_general_settings );

$admin_id = $I->haveUserInDatabase( 'admin', 'administrator' );
$post_id  = $I->havePostInDatabase(
	[
		'post_type'    => 'post',
		'post_status'  => 'publish',
		'post_title'   => 'test post',
		'post_content' => 'test post',
		'post_author'  => $admin_id,
	]
);
$page_id  = $I->havePostInDatabase(
	[
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'test page',
		'post_content' => 'test page',
		'post_author'  => $admin_id,
	]
);

$query = 'query GetPosts { posts { nodes { title } } }';

$I->sendGet( 'http://localhost/graphql?query=' . $query );

$I->seeResponseCodeIs( 200 );
$I->seeResponseIsJson();
$x_graphql_keys = $I->grabHttpHeader( 'X-GraphQL-Keys' );
$I->assertEmpty( $x_graphql_keys );

// Then test with Query Analyzer enabled.
$graphql_general_settings['query_analyzer_enabled'] = 'on';
update_option( 'graphql_general_settings', $graphql_general_settings );

$I->sendGet( 'http://localhost/graphql?query=' . $query );

$I->seeResponseCodeIs( 200 );
$I->seeResponseIsJson();
$x_graphql_keys = $I->grabHttpHeader( 'X-GraphQL-Keys' );
$x_graphql_url  = $I->grabHttpHeader( 'X-GraphQL-URL' );

$I->assertNotEmpty( $x_graphql_keys );

$I->assertContains( 'graphql:Query', explode( ' ', $x_graphql_keys ) );
$I->assertContains( 'operation:GetPosts', explode( ' ', $x_graphql_keys ) );

$I->assertNotEmpty( $x_graphql_url );
$post_cache_key = base64_encode( 'post:' . $post_id );
$I->assertContains( $post_cache_key, explode( ' ', $x_graphql_keys ) );

// query for edges
$query = '{ posts { edges { node { title } } } }';

$I->sendGet( 'http://localhost/graphql?query=' . $query );

$I->seeResponseCodeIs( 200 );
$I->seeResponseIsJson();
$x_graphql_keys = $I->grabHttpHeader( 'X-GraphQL-Keys' );
$x_graphql_url  = $I->grabHttpHeader( 'X-GraphQL-URL' );

codecept_debug( $x_graphql_keys );

$I->assertNotEmpty( $x_graphql_keys );
$I->assertNotEmpty( $x_graphql_url );
$post_cache_key = base64_encode( 'post:' . $post_id );
$I->assertContains( $post_cache_key, explode( ' ', $x_graphql_keys ) );
$list_post_key = 'list:post';
$I->assertContains( $list_post_key, explode( ' ', $x_graphql_keys ) );


// query for contentNodes.edges
$query = '{ contentNodes { edges { node { __typename } } } }';

$I->sendGet( 'http://localhost/graphql?query=' . $query );

$I->seeResponseCodeIs( 200 );
$I->seeResponseIsJson();
$x_graphql_keys = $I->grabHttpHeader( 'X-GraphQL-Keys' );
$x_graphql_url  = $I->grabHttpHeader( 'X-GraphQL-URL' );

$I->assertNotEmpty( $x_graphql_keys );
$I->assertNotEmpty( $x_graphql_url );
$post_cache_key = base64_encode( 'post:' . $post_id );
$I->assertContains( $post_cache_key, explode( ' ', $x_graphql_keys ) );
$list_post_key = 'list:post';
$I->assertContains( $list_post_key, explode( ' ', $x_graphql_keys ) );

// cleanup
$graphql_general_settings['query_analyzer_enabled'] = 'off';
update_option( 'graphql_general_settings', $graphql_general_settings );
