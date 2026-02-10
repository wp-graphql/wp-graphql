<?php

class TestCloneWithGroupCest {

	public function _before( FunctionalTester $I, \Codeception\Scenario $scenario ) {

		if ( ! $I->haveAcfProActive() ) {
			$I->markTestSkipped( 'Skipping test. ACF Pro is required for clone fields' );
		}

		/**
		 * This is an export of 58 different ACF Field Groups that are then cloned within the
		 * flexible_content field of the "Content Blocks" field group imported below
		 */
		$blocks = 'issue-172/acf-export-blocks.json';
		$I->importJson( $blocks );

		/**
		 * This is a field group with flexible_content that uses clone fields for each layout
		 * to clone the "Blocks" in the other field groups imported above
		 */
		$content_blocks = 'issue-172/acf-export-content-blocks.json';
		$I->importJson( $content_blocks );


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
		    possibleTypes {
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

	public function testImportedFieldGroupsShowInSchema(FunctionalTester $I) {
		$I->wantTo( 'Test the Imported Field Groups are shown in the Schema' );

		$variables = [
			'type' => 'ContentBlocks'
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
		$I->assertTrue( in_array( 'ContentBlocks_Fields', $interfaces, true ) );

		$I->assertTrue( in_array( 'blocks', $fields, true ) );

		$variables = [
			'type' => 'ContentBlocksBlocks_Layout'
		];
		$response = $this->_executeQuery( $I, null, $variables );

		$possible_types = array_map( static function( $interface ) {
			return $interface['name'];
		}, $response['data']['__type']['possibleTypes'] );

		$I->assertTrue( in_array( 'ContentBlocksBlocksAccordionLayout', $possible_types, true ) );
		$I->assertTrue( in_array( 'ContentBlocksBlocksAppCtaLayout', $possible_types, true ) );
		$I->assertTrue( in_array( 'ContentBlocksBlocksBlogPostsLayout', $possible_types, true ) );
		$I->assertTrue( in_array( 'ContentBlocksBlocksBrazeCardLayout', $possible_types, true ) );
		$I->assertTrue( in_array( 'ContentBlocksBlocksPriceComparisonLayout', $possible_types, true ) );



		$variables = [
			'type' => 'ContentBlocksBlocksAccordionLayout'
		];
		$response = $this->_executeQuery( $I, null, $variables );

		$fields = array_map( static function( $field ) {
			return $field['name'];
		}, $response['data']['__type']['fields'] );

		$interfaces = array_map( static function( $interface ) {
			return $interface['name'];
		}, $response['data']['__type']['interfaces'] );

		$I->assertTrue( in_array( 'ContentBlocksBlocksAccordionLayout_Fields', $interfaces, true ) );
		$I->assertTrue( in_array( 'ContentBlocksBlocks_Layout', $interfaces, true ) );
		$I->assertTrue( in_array( 'AcfFieldGroup', $interfaces, true ) );
		$I->assertTrue( in_array( 'BlockAccordion_Fields', $interfaces, true ) );
		$I->assertTrue( in_array( 'AcfFieldGroupFields', $interfaces, true ) );

		// the fields should NOT have an accordion field. That would exist if the
		// clone field was set to "prefix_name"
		$I->assertTrue( ! in_array( 'accordion', $fields, true ) );

		$variables = [
			'type' => 'ContentBlocksBlocksPriceComparisonLayout'
		];
		$response = $this->_executeQuery( $I, null, $variables );

		$fields = array_map( static function( $field ) {
			return $field['name'];
		}, $response['data']['__type']['fields'] );

		$interfaces = array_map( static function( $interface ) {
			return $interface['name'];
		}, $response['data']['__type']['interfaces'] );

		$I->assertTrue( in_array( 'BlockPriceComparison_Fields', $interfaces, true ) );
		$I->assertTrue( in_array( 'ContentBlocksBlocks_Layout', $interfaces, true ) );
		$I->assertTrue( in_array( 'AcfFieldGroup', $interfaces, true ) );
		$I->assertTrue( in_array( 'BlockPriceComparison_Fields', $interfaces, true ) );
		$I->assertTrue( in_array( 'AcfFieldGroupFields', $interfaces, true ) );

		$I->assertTrue( in_array( 'button', $fields, true ) );
		$I->assertTrue( in_array( 'intro', $fields, true ) );
		$I->assertTrue( in_array( 'pricesTable', $fields, true ) );
		$I->assertTrue( in_array( 'title', $fields, true ) );


	}


}
