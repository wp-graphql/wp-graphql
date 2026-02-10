<?php

class TestCloneWithRepeaterCest {

	public function _before( FunctionalTester $I, \Codeception\Scenario $scenario ) {

		$inactive_group = 'acf-export-clone-repeater.json';
		$I->importJson( $inactive_group );

		$pro_fields = 'tests-acf-pro-kitchen-sink.json';
		$I->importJson( $pro_fields );

		if ( ! $I->haveAcfProActive() ) {
			$I->markTestSkipped( 'Skipping test. ACF Pro is required for clone fields' );
		}

	}

	public function _getQuery() {
		return '
		query GetType($type: String!) {
		  __type(name: $type) {
		    name
		    kind
		    interfaces {
		      name
		    }
		    fields {
		      name
		      type {
	            name
	            kind
	            ofType {
	              name
	            }
	          }
		    }
		  }
		}
		';
	}

	public function _executeQuery( FunctionalTester $I, $query = null, $variables = null ) {

		if ( null === $query ) {
			$query = $this->_getQuery();
		}

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendPost( '/graphql', json_encode([
			'query' => $query,
			'variables' => $variables
		]));

		$I->seeResponseCodeIs( 200 );
		$I->seeResponseIsJson();
		$response = $I->grabResponse();
		$response = json_decode( $response, true );
		return $response;
	}

	public function testImportedFieldGroupsShowInSchema( FunctionalTester $I ) {

		$I->wantTo( 'Test the Imported Field Groups are shown in the Schema' );

		$variables = [
			'type' => 'Flowers'
		];
		$response = $this->_executeQuery( $I, null, $variables );

		$I->assertNotEmpty( $response['data']['__type']['fields'] );
		$I->assertNotEmpty( $response['data']['__type']['interfaces'] );

		$fields = array_map( static function( $field ) {
			return $field['name'];
		}, $response['data']['__type']['fields'] );

		$interfaces = array_map( static function( $interface ) {
			return $interface['name'];
		}, $response['data']['__type']['interfaces'] );

		$I->assertTrue( in_array( 'AcfFieldGroup', $interfaces, true ) );
		$I->assertTrue( in_array( 'Flowers_Fields', $interfaces, true ) );

		$I->assertTrue( in_array( 'color', $fields, true ) );
		$I->assertTrue( in_array( 'datePicker', $fields, true ) );
		$I->assertTrue( in_array( 'avatar', $fields, true ) );
		$I->assertTrue( in_array( 'range', $fields, true ) );



		$query = '
		query GetPageWithPlants($databaseId: ID!) {
		  page(id:$databaseId idType:DATABASE_ID) {
		    id
		    title
		    ...on WithAcfPlants {
		      plants {
		        name
		        clonedRepeater {
		          notClonedRepeater {
		            anotherName
		          }
		        }
		        notClonedRepeater {
		          anotherName
		        }
		      }
		    }
		  }
		}
		';

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendPost( '/graphql', json_encode([
			'query' => $query,
			'variables' => [
				'databaseId' => 0
			]
		]));

		$I->seeResponseCodeIs( 200 );
		$I->seeResponseIsJson();
		$response = $I->grabResponse();
		$response = json_decode( $response, true );

		$I->wantTo( 'Test that a query against the field group is valid' );

		// Validate that the query above was valid, returned data, and no errors
		$I->assertNotEmpty( $response['data'] );
		$I->assertArrayNotHasKey( 'errors', $response );
	}

	function testClonedFieldsAppliedAsInterfaces( FunctionalTester $I ) {
		$I->wantTo( 'Test Cloned Field Groups are applied as interfaces' );

		$variables = [
			'type' => 'Plants'
		];
		$response  = $this->_executeQuery( $I, null, $variables );

		$I->assertNotEmpty( $response['data']['__type']['fields'] );
		$I->assertNotEmpty( $response['data']['__type']['interfaces'] );

		$fields = array_map( static function( $field ) {
			return $field['name'];
		}, $response['data']['__type']['fields'] );

		$interfaces = array_map( static function( $interface ) {
			return $interface['name'];
		}, $response['data']['__type']['interfaces'] );

		$I->assertTrue( in_array( 'AcfFieldGroup', $interfaces, true ) );

		// Since the Plants Field Group clones the "Flowers" field group it implements the Flowers_Fields interface
		$I->assertTrue( in_array( 'Flowers_Fields', $interfaces, true ) );

		// The Field Group itself implements Plants_Fields
		$I->assertTrue( in_array( 'Plants_Fields', $interfaces, true ) );

		$I->assertTrue( in_array( 'color', $fields, true ) );
		$I->assertTrue( in_array( 'datePicker', $fields, true ) );
		$I->assertTrue( in_array( 'avatar', $fields, true ) );
		$I->assertTrue( in_array( 'range', $fields, true ) );

	}

	public function testClonedRepeaterFieldShowsInSchema(FunctionalTester $I ) {

		$I->wantTo( 'Test Cloned Repeater field shows in the Schema' );

		$variables = [
			'type' => 'Plants'
		];
		$response  = $this->_executeQuery( $I, null, $variables );

		$I->assertNotEmpty( $response['data']['__type']['fields'] );
		$I->assertNotEmpty( $response['data']['__type']['interfaces'] );

		$fields = array_map( static function( $field ) {
			return $field['name'];
		}, $response['data']['__type']['fields'] );

		$interfaces = array_map( static function( $interface ) {
			return $interface['name'];
		}, $response['data']['__type']['interfaces'] );

		// This field used to cause things to explode so this test ensures things
		// are working properly when cloning field groups that contain repeater fields
		$I->assertTrue( in_array( 'landMineRepeater', $fields, true ) );

		$field = $this->_findField( 'landMineRepeater', $response['data']['__type']['fields'] );

		$I->assertSame( 'LIST', $field['type']['kind'] );
		$I->assertSame( 'FlowersLandMineRepeater', $field['type']['ofType']['name'] );

		// Cloned Repeater is a prefixed clone field, we can assert that it returns a nested Object Type
		$I->assertTrue( in_array( 'clonedRepeater', $fields, true ) );

		// Find the clonedRepeaterField
		$field = $this->_findField( 'clonedRepeater', $response['data']['__type']['fields'] );

		$I->assertSame( 'OBJECT', $field['type']['kind'] );
		$I->assertNull( $field['type']['ofType'] );
		$I->assertSame( 'PlantsClonedRepeater', $field['type']['name'] );

		// Find the cloneRoots field (clone of the "flowers" field group, but with "prefix_name" set)
		$field = $this->_findField( 'cloneRoots', $response['data']['__type']['fields'] );

		$I->assertSame( 'OBJECT', $field['type']['kind'] );
		$I->assertNull( $field['type']['ofType'] );
		$I->assertSame( 'PlantsCloneRoots', $field['type']['name'] );
	}

	/**
	 * Given a field name, find the field associated with that name
	 *
	 * @param string $name The name of the field to find
	 * @param array  $fields The array of fields to search
	 *
	 * @return ?array Returns the field with the name being searched for
	 */
	public function _findField( string $name, array $fields ): ?array {
		$found_field = null;
		if ( ! empty( $fields ) ) {
			foreach ( $fields as $field ) {
				if ( isset( $field['name'] ) && $field['name'] === $name ) {
					$found_field = $field;
				}
			}
		}
	   return $found_field;
	}


}
