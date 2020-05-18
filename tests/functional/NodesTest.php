<?php
$query = '
{
  __type(name: "TermNode") {
    name
    kind
    possibleTypes {
      name
    }
  }
}
';
$I = new FunctionalTester( $scenario );
$I->wantTo( 'Ensure TermNodes implement the TermNode Interace' );


// Set the content-type so we get a proper response from the API.
$I->haveHttpHeader( 'Content-Type', 'application/json' );

// Query for the menu.
$I->sendPOST( 'http://wpgraphql.test/graphql', json_encode( [
	'query' => $query
] ) );

// Check response.
$I->seeResponseCodeIs( 200 );
$I->seeResponseIsJson();
$response       = $I->grabResponse();
$response_array = json_decode( $response, true );

// The query is valid and has no errors.
$I->assertArrayNotHasKey( 'errors', $response_array );

$possible_type_names = [];
foreach ( $response_array['data']['__type']['possibleTypes'] as $possible_type ) {
	$possible_type_names[] = $possible_type['name'];
}

$taxonomies = get_taxonomies([ 'show_in_graphql' => true ], 'objects' );
$expected_type_names = [];
foreach( $taxonomies as $taxonomy ) {
	$expected_type_names[] = ucfirst( $taxonomy->graphql_single_name );
}

sort($possible_type_names);
sort($expected_type_names);

$I->assertSame( $expected_type_names, $possible_type_names );
