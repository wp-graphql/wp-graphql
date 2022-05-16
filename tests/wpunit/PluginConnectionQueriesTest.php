<?php

class PluginConnectionQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $current_time;
	public $current_date;
	public $current_date_gmt;
	public $admin;

	public function setUp(): void {
		parent::setUp();
		$this->clearSchema();
		$this->current_time     = strtotime( 'now' );
		$this->current_date     = date( 'Y-m-d H:i:s', $this->current_time );
		$this->current_date_gmt = gmdate( 'Y-m-d H:i:s', $this->current_time );
		$this->admin            = $this->factory()->user->create( [
			'role' => 'administrator',
		] );
		if ( is_multisite() ) {
			grant_super_admin( $this->admin );
		}

	}

	public function tearDown(): void {
		// your tear down methods here
		$this->clearSchema();
		// then
		wp_logout();
		parent::tearDown();
	}

	/**
	 * testPluginsQuery
	 * This tests querying for a list of plugins.
	 * The test suite should have Hello Dolly and Akismet plugins, so this
	 * should return those plugins.
	 *
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

		if ( is_multisite() ) {
			grant_super_admin( $this->admin );
		}
		wp_set_current_user( $this->admin );
		$actual = $this->graphql( [ 'query' => $query ] );

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
	 * Tests querying for plugins with pagination args.
	 */
	public function testPluginsQueryPagination() {
		wp_set_current_user( $this->admin );

		$query = '
			query testPlugins($first: Int, $after: String, $last: Int, $before: String ) {
				plugins(first: $first, last: $last, before: $before, after: $after) {
					pageInfo {
						endCursor
						hasNextPage
						hasPreviousPage
						startCursor
					}
					nodes {
						id
						name
					}
				}
			}
		';

		// Get all for comparison
		$variables = [
			'first'  => 100,
			'after'  => null,
			'last'   => null,
			'before' => null,
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );

		$nodes = $actual['data']['plugins']['nodes'];

		// Get first two plugins
		$variables['first'] = 2;

		$expected = array_slice( $nodes, 0, $variables['first'], true );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual['data']['plugins']['nodes'] );

		// Test with empty `after`.
		$variables['after'] = '';
		$actual             = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual['data']['plugins']['nodes'] );

		$variables = [
			'first'  => null,
			'after'  => null,
			'last'   => 2,
			'before' => null,
		];

		$expected = array_slice( $nodes, count( $nodes ) - $variables['last'], null, true );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual['data']['plugins']['nodes'] );

		// Test with empty `before`.
		$variables['before'] = '';
		$actual              = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual['data']['plugins']['nodes'] );
	}

	/**
	 * Tests querying for plugin with where args.
	 */
	public function testPluginsQueryWithWhereArgs() {
		$active_plugin   = 'WP GraphQL';
		$inactive_plugin = 'Akismet Anti-Spam';

		wp_set_current_user( $this->admin );

		$query = '
			query testPlugins($where: RootQueryToPluginConnectionWhereArgs ) {
				plugins(where: $where) {
					nodes {
						id
						name
					}
				}
			}
		';

		// Filter by search term

		$variables = [
			'where' => [
				'search' => $active_plugin,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertIsValidQueryResponse( $actual );

		$actual_plugins = array_column( $actual['data']['plugins']['nodes'], 'name' );
		$this->assertContains( $active_plugin, $actual_plugins );
		$this->assertNotContains( $inactive_plugin, $actual_plugins );

		// Filter by status
		// Active status
		$variables = [
			'where' => [
				'status' => 'ACTIVE',
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		codecept_debug( $actual );

		$this->assertIsValidQueryResponse( $actual );

		$actual_plugins = array_column( $actual['data']['plugins']['nodes'], 'name' );
		$this->assertContains( $active_plugin, $actual_plugins );
		$this->assertNotContains( $inactive_plugin, $actual_plugins );

		// Inactive status
		$variables['where']['status'] = 'INACTIVE';

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertIsValidQueryResponse( $actual );

		$actual_plugins = array_column( $actual['data']['plugins']['nodes'], 'name' );
		$this->assertContains( $inactive_plugin, $actual_plugins );
		$this->assertNotContains( $active_plugin, $actual_plugins );

		// Filter by statii
		$variables = [
			'where' => [
				'stati' => [ 'INACTIVE' ],
			],
		];
		$actual    = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertIsValidQueryResponse( $actual );

		$actual_plugins = array_column( $actual['data']['plugins']['nodes'], 'name' );
		$this->assertContains( $inactive_plugin, $actual_plugins );
		$this->assertNotContains( $active_plugin, $actual_plugins );
	}

	/**
	 * Assert that no plugins are returned when the user does not have the `update_plugins` cap
	 */
	public function testPluginsQueryWithoutAuth() {

		wp_logout();

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

		$actual = $this->graphql( [ 'query' => $query ] );

		$this->assertEmpty( $actual['data']['plugins']['edges'] );
		$this->assertEmpty( $actual['data']['plugins']['nodes'] );

	}

	/**
	 * testPluginQuery
	 *
	 * @since 0.0.5
	 */
	public function testPluginQuery() {

		$path      = 'wp-graphql/wp-graphql.php';
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'plugin', $path );

		codecept_debug( $global_id );

		$query = '
		{
			plugin(id: "' . $global_id . '"){
				id
				name
				author
				authorUri
				description
				name
				pluginUri
				version
				path
			}
		}
		';

		wp_set_current_user( $this->admin );
		$actual = $this->graphql( [ 'query' => $query ] );

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
		$this->assertTrue( ( is_string( $plugin_version ) || null === $plugin_version ) );

		$this->assertSame( $path, $actual['data']['plugin']['path'] );

	}
}
