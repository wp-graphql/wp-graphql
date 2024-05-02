<?php

class PluginConnectionQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $admin;
	public $current_date_gmt;
	public $current_date;
	public $current_time;

	public function setUp(): void {
		parent::setUp();
		$this->clearSchema();
		$this->current_time     = strtotime( 'now' );
		$this->current_date     = date( 'Y-m-d H:i:s', $this->current_time );
		$this->current_date_gmt = gmdate( 'Y-m-d H:i:s', $this->current_time );
		$this->admin            = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
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

	public function getQuery() {
		return '
			query pluginsQuery( $first: Int, $last:Int, $after:String, $before:String, $where:RootQueryToPluginConnectionWhereArgs ) {
				plugins( first: $first, last: $last, after: $after, before: $before, where: $where ) {
					pageInfo {
						hasNextPage
						hasPreviousPage
						startCursor
						endCursor
					}
					edges {
						cursor
						node {
							id
							path
						}
					}
					nodes {
						path
						name
					}
				}
			}
		';
	}

	public function testForwardPagination() {
		wp_set_current_user( $this->admin );
		$query = $this->getQuery();

		// The list of plugins might change, so we'll reuse this to check late.
		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'first' => 100,
				],
			]
		);

		// Confirm its valid.
		$this->assertResponseIsValid( $actual );
		$this->assertNotEmpty( $actual['data']['plugins']['edges'][0]['node']['path'] );

		// Store for use by $expected.
		$wp_query = $actual['data']['plugins'];

		/**
		 * Test the first two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables = [
			'first' => 2,
		];

		// Run the GraphQL Query
		$expected = $wp_query;
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( false, $actual['data']['plugins']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['plugins']['pageInfo']['hasNextPage'] );

		/**
		 * Test with empty offset.
		 */
		$variables['after'] = '';
		$expected           = $actual;

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual );

		/**
		 * Test the next two results.
		 */

		// Set the variables to use in the GraphQL query.
		// We dont have enough to paginate twice.
		$variables['after'] = $actual['data']['plugins']['pageInfo']['startCursor'];

		// Run the GraphQL Query
		$expected          = $wp_query;
		$expected['edges'] = array_slice( $expected['edges'], 1, 2, false );
		$expected['nodes'] = array_slice( $expected['nodes'], 1, 2, false );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['plugins']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $actual['data']['plugins']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results.
		 */

		// Set the variables to use in the GraphQL query.
		// We dont have enough to paginate twice.
		$variables['after'] = $actual['data']['plugins']['pageInfo']['startCursor'];

		// Run the GraphQL Query
		$expected          = $wp_query;
		$expected['edges'] = array_slice( $expected['edges'], 2, null, false );
		$expected['nodes'] = array_slice( $expected['nodes'], 2, null, false );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		// Theres only one item, so we cant assertValidPagination()
		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertEquals( 1, count( $actual['data']['plugins']['edges'] ) );

		$plugin_path = $expected['nodes'][0]['path'];
		$cursor      = $this->toRelayId( 'arrayconnection', $plugin_path );

		$this->assertEquals( $plugin_path, $actual['data']['plugins']['edges'][0]['node']['path'] );
		$this->assertEquals( $plugin_path, $actual['data']['plugins']['nodes'][0]['path'] );
		$this->assertEquals( $cursor, $actual['data']['plugins']['edges'][0]['cursor'] );
		$this->assertEquals( $cursor, $actual['data']['plugins']['pageInfo']['startCursor'] );
		$this->assertEquals( $cursor, $actual['data']['plugins']['pageInfo']['endCursor'] );

		$this->assertEquals( true, $actual['data']['plugins']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $actual['data']['plugins']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results are equal to `last:2`.
		 */
		$variables = [
			'last' => 100,
		];
		$expected  = $wp_query;

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual['data']['plugins'] );
	}

	public function testBackwardPagination() {
		wp_set_current_user( $this->admin );
		$query = $this->getQuery();

		// The list of plugins might change, so we'll reuse this to check late.
		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'last' => 100,
				],
			]
		);

		// Confirm its valid.
		$this->assertResponseIsValid( $actual );
		$this->assertNotEmpty( $actual['data']['plugins']['edges'][0]['node']['path'] );

		// Store for use by $expected.
		$wp_query = $actual['data']['plugins'];

		/**
		 * Test the first two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables = [
			'last' => 2,
		];

		// Run the GraphQL Query
		$expected          = $wp_query;
		$expected['edges'] = array_slice( $expected['edges'], 1, 2, false );
		$expected['nodes'] = array_slice( $expected['nodes'], 1, 2, false );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['plugins']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $actual['data']['plugins']['pageInfo']['hasNextPage'] );

		/**
		 * Test with empty offset.
		 */
		$variables['before'] = '';
		$expected            = $actual;

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual );

		/**
		 * Test the next two results.
		 */

		// Set the variables to use in the GraphQL query.
		// We dont have enough to paginate twice.
		$variables['before'] = $actual['data']['plugins']['pageInfo']['endCursor'];

		// Run the GraphQL Query
		$expected          = $wp_query;
		$expected['edges'] = array_slice( array_reverse( $expected['edges'] ), 1, 2, false );
		$expected['edges'] = array_reverse( $expected['edges'] );
		$expected['nodes'] = array_slice( array_reverse( $expected['nodes'] ), 1, 2, false );
		$expected['nodes'] = array_reverse( $expected['nodes'] );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( false, $actual['data']['plugins']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['plugins']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results.
		 */

		// Set the variables to use in the GraphQL query.
		// We dont have enough to paginate twice.
		$variables['before'] = $actual['data']['plugins']['pageInfo']['endCursor'];

		// Run the GraphQL Query
		$expected          = $wp_query;
		$expected['edges'] = array_slice( array_reverse( $expected['edges'] ), 2, null, false );
		$expected['nodes'] = array_slice( array_reverse( $expected['nodes'] ), 2, null, false );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		// Theres only one item, so we cant assertValidPagination()
		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertEquals( 1, count( $actual['data']['plugins']['edges'] ) );

		$plugin_path = $expected['nodes'][0]['path'];
		$cursor      = $this->toRelayId( 'arrayconnection', $plugin_path );

		$this->assertEquals( $plugin_path, $actual['data']['plugins']['edges'][0]['node']['path'] );
		$this->assertEquals( $plugin_path, $actual['data']['plugins']['nodes'][0]['path'] );
		$this->assertEquals( $cursor, $actual['data']['plugins']['edges'][0]['cursor'] );
		$this->assertEquals( $cursor, $actual['data']['plugins']['pageInfo']['startCursor'] );
		$this->assertEquals( $cursor, $actual['data']['plugins']['pageInfo']['endCursor'] );

		$this->assertEquals( false, $actual['data']['plugins']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['plugins']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results are equal to `last:2`.
		 */
		$variables = [
			'first' => 100,
		];
		$expected  = $wp_query;

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual['data']['plugins'] );
	}

	public function testQueryWithFirstAndLast() {
		wp_set_current_user( $this->admin );

		$query = $this->getQuery();

		// The list of plugins might change, so we'll reuse this to check late.
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'first' => 100,
				],
			]
		);

		$after_cursor  = $actual['data']['plugins']['edges'][0]['cursor'];
		$before_cursor = $actual['data']['plugins']['edges'][2]['cursor'];

		// Get 5 items, but between the bounds of a before and after cursor.
		$variables = [
			'first'  => 5,
			'after'  => $after_cursor,
			'before' => $before_cursor,
		];

		$expected = $actual['data']['plugins']['nodes'][1];
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['plugins']['nodes'][0] );

		/**
		 * Test `last`.
		 */
		$variables['last'] = 5;

		// Using first and last should throw an error.
		$actual = graphql( compact( 'query', 'variables' ) );

		$this->assertArrayHasKey( 'errors', $actual );

		unset( $variables['first'] );

		// Get 5 items, but between the bounds of a before and after cursor.
		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['plugins']['nodes'][0] );
	}

	/**
	 * Tests querying for plugin with where args.
	 */
	public function testPluginsQueryWithWhereArgs() {
		$query = $this->getQuery();

		$active_plugin_name = 'WPGraphQL';
		$active_plugin      = 'wp-graphql/wp-graphql.php';
		global $wp_version;

		$inactive_plugin = 'akismet/akismet.php';

		wp_set_current_user( $this->admin );

		// Filter by search term

		$variables = [
			'where' => [
				'search' => $active_plugin_name,
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertResponseIsValid( $actual );

		$actual_plugins = array_column( $actual['data']['plugins']['nodes'], 'path' );
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

		$this->assertResponseIsValid( $actual );

		$actual_plugins = array_column( $actual['data']['plugins']['nodes'], 'path' );
		$this->assertContains( $active_plugin, $actual_plugins );
		$this->assertNotContains( $inactive_plugin, $actual_plugins );

		// Inactive status
		$variables['where']['status'] = 'INACTIVE';

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertResponseIsValid( $actual );

		$actual_plugins = array_column( $actual['data']['plugins']['nodes'], 'path' );
		$this->assertContains( $inactive_plugin, $actual_plugins );
		$this->assertNotContains( $active_plugin, $actual_plugins );

		// Filter by statii
		$variables = [
			'where' => [
				'stati' => [ 'INACTIVE' ],
			],
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertResponseIsValid( $actual );

		$actual_plugins = array_column( $actual['data']['plugins']['nodes'], 'path' );
		$this->assertContains( $inactive_plugin, $actual_plugins );
		$this->assertNotContains( $active_plugin, $actual_plugins );
	}

	/**
	 * Assert that no plugins are returned when the user does not have the `update_plugins` cap
	 */
	public function testPluginsQueryWithoutAuth() {

		wp_logout();

		$actual = graphql( [ 'query' => $this->getQuery() ] );

		$this->assertEmpty( $actual['data']['plugins']['edges'] );
		$this->assertEmpty( $actual['data']['plugins']['nodes'] );
	}

	/**
	 * Common asserts for testing pagination.
	 *
	 * @param array $expected An array of the results from WordPress. When testing backwards pagination, the order of this array should be reversed.
	 * @param array $actual The GraphQL results.
	 */
	public function assertValidPagination( $expected, $actual ) {
		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertEquals( 2, count( $actual['data']['plugins']['edges'] ) );

		$first_plugin_path  = $expected['nodes'][0]['path'];
		$second_plugin_path = $expected['nodes'][1]['path'];

		$start_cursor = $this->toRelayId( 'arrayconnection', $first_plugin_path );
		$end_cursor   = $this->toRelayId( 'arrayconnection', $second_plugin_path );

		$this->assertEquals( $first_plugin_path, $actual['data']['plugins']['edges'][0]['node']['path'] );
		$this->assertEquals( $first_plugin_path, $actual['data']['plugins']['nodes'][0]['path'] );
		$this->assertEquals( $start_cursor, $actual['data']['plugins']['edges'][0]['cursor'] );
		$this->assertEquals( $second_plugin_path, $actual['data']['plugins']['edges'][1]['node']['path'] );
		$this->assertEquals( $second_plugin_path, $actual['data']['plugins']['nodes'][1]['path'] );
		$this->assertEquals( $end_cursor, $actual['data']['plugins']['edges'][1]['cursor'] );
		$this->assertEquals( $start_cursor, $actual['data']['plugins']['pageInfo']['startCursor'] );
		$this->assertEquals( $end_cursor, $actual['data']['plugins']['pageInfo']['endCursor'] );
	}
}
