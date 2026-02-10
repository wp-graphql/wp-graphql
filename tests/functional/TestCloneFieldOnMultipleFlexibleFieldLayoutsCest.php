<?php

class TestCloneFieldOnMultipleFlexibleFieldLayoutsCest {

	public function _before( FunctionalTester $I, \Codeception\Scenario $scenario ) {

		$acf_json = 'issue-197/acf-export-issue-197.json';
		$I->importJson( $acf_json );

		if ( ! $I->haveAcfProActive() ) {
			$I->markTestSkipped( 'Skipping test. ACF Pro is required for clone fields' );
		}

	}

	public function testAcfFieldGroupsShowInSchema( FunctionalTester $I ) {

		$I->wantTo( 'Test imported field groups show in schema when querying the possible types of AcfFieldGroup' );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );

		$query = '
		query GetAcfFieldGroups {
		  __type(name:"AcfFieldGroup") {
		    name
		    possibleTypes {
		      name
		    }
		  }
		}
		';

		$I->sendPost( '/graphql', json_encode([
			'query' => $query
		]));

		$I->seeResponseCodeIs( 200 );
		$I->seeResponseIsJson();
		$response = $I->grabResponse();
		$response = json_decode( $response, true );

		$I->assertSame( 'AcfFieldGroup', $response['data']['__type']['name'] );
		$I->assertNotEmpty( $response['data']['__type']['possibleTypes'] );

		$possible_types = array_map( static function( $possible_type ) {
			return $possible_type['name'];
		}, $response['data']['__type']['possibleTypes'] );

		$I->assertTrue( in_array( 'Section', $possible_types, true ) );
		$I->assertTrue( in_array( 'SectionBackgroundColorGroup', $possible_types, true ) );
		$I->assertTrue( in_array( 'PageContent', $possible_types, true ) );
		$I->assertTrue( in_array( 'PostContent', $possible_types, true ) );
		$I->assertTrue( in_array( 'CareersFields', $possible_types, true ) );
		$I->assertTrue( in_array( 'ClassFinderFields', $possible_types, true ) );

	}

}
