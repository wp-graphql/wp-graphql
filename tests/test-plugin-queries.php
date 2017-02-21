<?php

/**
 * WPGraphQL Test Plugin Queries
 * This tests post queries (singular and plural) checking to see if the available fields return the expected response
 * @package WPGraphQL
 * @since 0.0.5
 */
class WP_GraphQL_Test_Plugin_Queries extends WP_UnitTestCase {

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
			'role' => 'admin',
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
		  plugins{
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

		$expected = [
			'data' => [
				'plugins' => [
					'edges' => [
						[
							'node' => [
								'id' => 'cGx1Z2luOkFraXNtZXQ=',
								'name' => 'Akismet',
							],
						],
						[
							'node' => [
								'id' => 'cGx1Z2luOkhlbGxvIERvbGx5',
								'name' => 'Hello Dolly',
							],
						],
					],
				],
			],
		];

		$this->assertEquals( $expected, $actual );

	}

	/**
	 * testPluginQuery
	 * @since 0.0.5
	 */
	public function testPluginQuery() {

		$query = '
		{
		  plugin(id: "cGx1Z2luOkFraXNtZXQ="){
		    id
		    name
		  }
		}
		';

		$actual = do_graphql_request( $query );

		$expected = [
			'data' => [
				'plugin' => [
					'id' => 'cGx1Z2luOkFraXNtZXQ=',
					'name' => 'Akismet',
				],
			],
		];

		$this->assertEquals( $expected, $actual );

	}

}
