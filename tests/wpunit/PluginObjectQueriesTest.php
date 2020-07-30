<?php

class PluginObjectQueriesTest extends \Codeception\TestCase\WPTestCase {

	public $admin;

	public function setUp(): void {
		parent::setUp();
		WPGraphQL::clear_schema();
		$this->admin = $this->factory()->user->create( [
			'role' => 'administrator',
		] );
	}

	public function tearDown(): void {
		wp_logout();
		WPGraphQL::clear_schema();
		parent::tearDown();
	}

	/**
	 * Quick function for comparing semantic versioning
	 *
	 * @param string $versionA	string representation of version number
	 * @param string $versionB 	string representation of version number
	 * @return string|boolean 	returns true|false if $versionA <> $versionB, and "equals" if $versionA == $versionB
	 */
	public function compareSemantics( $versionA, $versionB )
	{
		$a = explode( '.', $versionA );
		$b = explode( '.', $versionB );
		if ( ! empty( $a[0] ) )
		{
			if ( empty( $b[0] ) ) return true;
			if ( ( int ) $a[0] > ( int ) $b[0] ) return true;
			elseif ( ( int ) $a[0] < ( int ) $b[0] ) return false;
		}
		if ( ! empty( $a[1] ) )
		{
			if ( empty( $b[1] ) ) return true;
			if ( ( int ) $a[1] > ( int ) $b[1] ) return true;
			elseif ( ( int ) $a[1] < ( int ) $b[1] ) return false;
		}
		if ( ! empty( $a[2] ) )
		{
			if ( empty( $b[2] ) ) return true;
			if ( ( int ) $a[2] > ( int ) $b[2] ) return true;
			elseif ( ( int ) $a[2] < ( int ) $b[2] ) return false;
		}

		return 'equals';
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
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'plugin', 'wp-graphql/wp-graphql.php' );

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
		wp_set_current_user( $this->admin );
		$actual = do_graphql_request( $query );

		codecept_debug( $actual );

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
		wp_set_current_user( $this->admin );
		$actual = do_graphql_request( $query );

		codecept_debug( $actual );

		/**
		 * Establish the expectation for the output of the query
		 */
		$expected = [
			'plugin' => null,
		];

		$this->assertEquals( $expected, $actual['data'] );
	}

}
