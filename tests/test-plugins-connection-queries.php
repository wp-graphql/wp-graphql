<?php

/**
 * WPGraphQL Test Plugins Connection Queries
 *
 * @package WPGraphQL
 */
class WP_GraphQL_Test_Plugins_Connection_Queries extends WP_UnitTestCase {

	/**
	 * This function is run before each method.
	 */
	public function setUp() {
		parent::setUp();
	}

	/**
	 * This function is run after each method.
	 */
	public function tearDown() {
		parent::tearDown();
	}

	/**
	 * This tests querying for plugins to ensure that we're getting back a proper connection.
	 */
	public function testPluginsConnectionQuery() {

		$query = '
		{
		  plugins(last: 1){
		    edges{
		      node{
		        id
		        name
		      }
		    }
		  }
		}
		';

		$actual = do_graphql_request( $query );

		$this->assertNotEmpty( $actual['data']['plugins']['edges'] );
		$this->assertNotEmpty( $actual['data']['plugins']['edges'][0]['node']['id'] );
		$this->assertNotEmpty( $actual['data']['plugins']['edges'][0]['node']['name'] );
	}
}
