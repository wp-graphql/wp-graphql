<?php

/**
 * WPGraphQL Test Plugin Queries
 * This tests post queries (singular and plural) checking to see if the available fields return the expected response
 * @package WPGraphQL
 * @since 0.0.5
 */
class WP_GraphQL_Test_Plugin_Queries extends WP_UnitTestCase {

	public $current_time;
	public $current_date;
	public $current_date_gmt;
	public $admin;


	/**
	 * This function is run before each method
	 * @since 0.0.5
	 */
	public function setUp() {
		parent::setUp();

		$this->current_time = strtotime( 'now' );
		$this->current_date = date( 'Y-m-d H:i:s', $this->current_time );
		$this->current_date_gmt = gmdate( 'Y-m-d H:i:s', $this->current_time );
		$this->admin = $this->factory->user->create( [
			'role' => 'administrator',
		] );

	}

	/**
	 * Runs after each method.
	 * @since 0.0.5
	 */
	public function tearDown() {
		parent::tearDown();
	}

	/**
	 * testPluginsQuery
	 * This tests querying for a list of plugins.
	 * The test suite should have Hello Dolly and Akismet plugins, so this
	 * should return those plugins.
	 * @since 0.0.5
	 */
	public function testPluginsQuery() {

		$query = '
		{
		  plugins {
		    edges {
		      node {
		        id
		        name
		      }
		    }
		    nodes {
		      id
		    }
		  }
		}
		';

		$actual = do_graphql_request( $query );

		/**
		 * We don't really care what the specifics are because the default plugins could change at any time
		 * and we don't care to maintain the exact match, we just want to make sure we are
		 * properly getting a theme back in the query
		 */
		$this->assertNotEmpty( $actual['data']['plugins']['edges'] );
		$this->assertNotEmpty( $actual['data']['plugins']['edges'][0]['node']['id'] );
		$this->assertNotEmpty( $actual['data']['plugins']['edges'][0]['node']['name'] );
		$this->assertNotEmpty( $actual['data']['plugins']['nodes'][0]['id'] );
		$this->assertEquals( $actual['data']['plugins']['nodes'][0]['id'], $actual['data']['plugins']['edges'][0]['node']['id'] );

		foreach ( $actual['data']['plugins']['edges'] as $key => $edge ) {
			$this->assertEquals( $actual['data']['plugins']['nodes'][ $key ]['id'], $edge['node']['id'] );
		}

	}

	/**
	 * testPluginQuery
	 * @since 0.0.5
	 */
	public function testPluginQuery() {

		$query = '
		{
		  plugin(id: "cGx1Z2luOkhlbGxvIERvbGx5"){
		    id
		    name
		    author
		    authorUri
		    description
		    name
		    pluginUri
		    version
		  }
		}
		';

		$actual = do_graphql_request( $query );

		/**
		 * We don't really care what the specifics are because the default plugins could change at any time
		 * and we don't care to maintain the exact match, we just want to make sure we are
		 * properly getting a theme back in the query
		 */
		$this->assertNotEmpty( $actual['data']['plugin']['id'] );
		$this->assertNotEmpty( $actual['data']['plugin']['name'] );

		$plugin_id = $actual['data']['plugin']['id'];
		$this->assertTrue( ( is_string( $plugin_id ) || null === $plugin_id ) );

		$plugin_name = $actual['data']['plugin']['name'];
		$this->assertTrue( ( is_string( $plugin_name ) || null === $plugin_name ) );

		$plugin_author = $actual['data']['plugin']['author'];
		$this->assertTrue( ( is_string( $plugin_author ) || null === $plugin_author ) );

		$plugin_author_uri = $actual['data']['plugin']['authorUri'];
		$this->assertTrue( ( is_string( $plugin_author_uri ) || null === $plugin_author_uri ) );

		$plugin_description = $actual['data']['plugin']['description'];
		$this->assertTrue( ( is_string( $plugin_description ) || null === $plugin_description ) );

		$plugin_uri = $actual['data']['plugin']['pluginUri'];
		$this->assertTrue( ( is_string( $plugin_uri ) || null === $plugin_uri ) );

		$plugin_version = $actual['data']['plugin']['version'];
		$this->assertTrue( ( is_float( $plugin_version ) || null === $plugin_version ) );

	}

}
