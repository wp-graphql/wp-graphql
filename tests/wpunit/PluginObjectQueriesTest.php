<?php

class PluginObjectQueriesTest extends \Codeception\TestCase\WPTestCase {

	public function setUp() {
		parent::setUp();
	}

	public function tearDown() {
		parent::tearDown();
	}

	/**
	 * testPluginQuery
	 *
	 * This tests creating a single plugin with data and retrieving said plugin via a GraphQL query
	 *
	 * @since 0.0.5
	 */
	public function testPluginQuery() {

		/**
		 * Create the global ID based on the plugin_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'plugin', 'Hello Dolly' );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			plugin(id: \"{$global_id}\") {
				author
				authorUri
				description
				id
				name
				pluginUri
				version
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		/**
		 * We don't really care what the specifics are because the values could change at any time
		 * and we don't care to maintain the exact match, we just want to make sure we are
		 * properly getting a plugin back in the query
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
		$this->assertTrue( ( is_string( $plugin_version ) || null === $plugin_version ) );

	}

	/**
	 * testPluginQueryWherePluginDoesNotExist
	 *
	 * Tests a query for non existant plugin.
	 *
	 * @since 0.0.5
	 */
	public function testPluginQueryWherePluginDoesNotExist() {
		/**
		 * Create the global ID based on the plugin_type and the created $id
		 */
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'plugin', 'doesNotExist' );

		/**
		 * Create the query string to pass to the $query
		 */
		$query = "
		query {
			plugin(id: \"{$global_id}\") {
				version
			}
		}";

		/**
		 * Run the GraphQL query
		 */
		$actual = do_graphql_request( $query );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'data' => [
				'plugin' => null,
			],
			'errors' => [
				[
					'message' => 'No plugin was found with the name doesNotExist',
					'locations' => [
						[
							'line' => 3,
							'column' => 4,
						],
					],
					'path' => [
						'plugin',
					],
					'category' => 'user',
				],
			],
		];

		$this->assertEquals( $expected, $actual );
	}

}