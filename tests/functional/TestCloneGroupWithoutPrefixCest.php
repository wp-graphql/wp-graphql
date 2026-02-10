<?php

class TestCloneGroupWithoutPrefixCest {

	public function _before( FunctionalTester $I, \Codeception\Scenario $scenario ) {

		$acf_json = 'issue-172-b/acf-export-issue-172-b.json';
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

		$I->assertTrue( in_array( 'PostCategoryOptions', $possible_types, true ) );
		$I->assertTrue( in_array( 'AuthorCustomFields', $possible_types, true ) );
		$I->assertTrue( in_array( 'ContentGridOption', $possible_types, true ) );
		$I->assertTrue( in_array( 'AcfPageOptions', $possible_types, true ) );
		$I->assertTrue( in_array( 'AcfPageOptionsPageOptions', $possible_types, true ) );
		$I->assertTrue( in_array( 'Schema', $possible_types, true ) );

	}

	public function testQueryPostWithPostSectionsAndAssertNoErrors( FunctionalTester $I ) {

		$I->wantTo( 'Post Sections can be queried without errors' );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );

		$query = '
		query GetPostSections {
		  post(id:20754 idType:DATABASE_ID){
		    id
		    title
		    postSections {
		      postSections {
		        __typename
		        ...on PostSectionsPostSectionsContentLayout {
		          dropcap
		          subLayout {
		            contentWidth
		          }
		        }
		      }
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

		// The query should be valid and contain no errors
		// We're not really testing for data resolution here, just that the
		// query is valid and does not contain errors
		$I->assertArrayNotHasKey( 'errors', $response, 'The response has no errors, meaning the query was valid, even if no data was returned');

	}


}
