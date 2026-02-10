<?php

class TestCloneFieldsCest {

	public function _before( FunctionalTester $I, \Codeception\Scenario $scenario ) {

		$inactive_group = 'tests-inactive-group-for-cloning.json';
		$I->importJson( $inactive_group );

		$pro_fields = 'tests-acf-pro-kitchen-sink.json';
		$I->importJson( $pro_fields );

		if ( ! $I->haveAcfProActive() ) {
			$I->markTestSkipped( 'Skipping test. ACF Pro is required for clone fields' );
		}

	}

	public function testClonedFieldGroupAppliedAsInterface( FunctionalTester $I ) {

		$I->wantTo( 'Test Cloned Field Groups are applied as Interface' );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendPost( '/graphql', json_encode([
			'query' => '
			query GetType($type: String!) {
			  __type(name: $type) {
			    name
			    kind
			    interfaces {
			      name
			    }
			    fields {
			      name
			    }
			  }
			}
			',
			'variables' => [
				'type' => 'AcfProKitchenSink'
			]
		]));

		$I->seeResponseCodeIs( 200 );
		$I->seeResponseIsJson();
		$response = $I->grabResponse();
		$response = json_decode( $response, true );

		$I->assertNotEmpty( $response['data']['__type']['fields'] );
		$I->assertNotEmpty( $response['data']['__type']['interfaces'] );

		$fields = array_map( static function( $field ) {
			return $field['name'];
		}, $response['data']['__type']['fields'] );

		$interfaces = array_map( static function( $interface ) {
			return $interface['name'];
		}, $response['data']['__type']['interfaces'] );

		// The Cloned Image Field and Cloned Text Fields should exist on the AcfProKitchenSink Type
		$I->assertTrue( in_array( 'clonedImageField', $fields, true ) );
		$I->assertTrue( in_array( 'clonedTextField', $fields, true ) );

		// Since the ACF Pro Kitchen Sink field group cloned the entire field group, the interface for the entire field group
		// should be applied
		$I->assertTrue( in_array( 'InactiveGroupForCloning_Fields', $interfaces, true ) );
	}

	public function testClonedFieldIndividuallyDoesNotApplyClonedGroupAsInterface( FunctionalTester $I ) {

		$I->wantTo( 'Test Cloning Individual Fields Does Not Apply the cloned Fields group as an Interface' );

		$I->haveHttpHeader( 'Content-Type', 'application/json' );
		$I->sendPost( '/graphql', json_encode([
			'query' => '
			query GetType($type: String!) {
			  __type(name: $type) {
			    name
			    kind
			    interfaces {
			      name
			    }
			    fields {
			      name
			    }
			  }
			}
			',
			'variables' => [
				'type' => 'AcfProKitchenSinkFlexibleContentLayoutWithClonedGroupLayout'
			]
		]));

		$I->seeResponseCodeIs( 200 );
		$I->seeResponseIsJson();
		$response = $I->grabResponse();
		$response = json_decode( $response, true );

		$I->assertNotEmpty( $response['data']['__type']['fields'] );
		$I->assertNotEmpty( $response['data']['__type']['interfaces'] );

		$fields = array_map( static function( $field ) {
			return $field['name'];
		}, $response['data']['__type']['fields'] );

		$interfaces = array_map( static function( $interface ) {
			return $interface['name'];
		}, $response['data']['__type']['interfaces'] );

		// The Cloned Image Field and Cloned Text Fields have been properly cloned onto the AcfProKitchenSinkFlexibleContentLayoutWithClonedGroup
		$I->assertTrue( in_array( 'clonedImageField', $fields, true ) );
		$I->assertTrue( in_array( 'clonedTextField', $fields, true ) );

		// BUT! Since the fields were cloned individually, the Interface for the cloned fields group is NOT applied
		$I->assertFalse( in_array( 'InactiveGroupForCloning_Fields', $interfaces, true ) );

	}
}
