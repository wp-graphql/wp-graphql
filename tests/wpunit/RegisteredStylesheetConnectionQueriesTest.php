<?php

class RegisteredStylesheetConnectionQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	private $admin;

	public function setUp(): void {
		parent::setUp();
		$this->admin = $this->factory()->user->create( [ 'role' => 'administrator' ] );
	}

	public function tearDown(): void {
		parent::tearDown();
		unset( $wp_styles );
	}

	public function getQuery() {
		return '
			query testRegisteredStylesheets($first: Int, $after: String, $last: Int, $before: String ) {
				registeredStylesheets(first: $first, last: $last, before: $before, after: $after) {
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
							handle
						}
					}
					nodes {
						conditional
						dependencies {
							handle
						}
						group
						handle
						isRtl
						media
						path
						rel
						src
						suffix
						title
						version
					}
				}
			}
		';
	}

	public function testForwardPagination() {
		wp_set_current_user( $this->admin );
		$query = $this->getQuery();

		// The list of registeredStylesheets might change, so we'll reuse this to check late.
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'first' => 4,
				],
			]
		);

		// Confirm it's valid.
		$this->assertResponseIsValid( $actual );
		$this->assertNotEmpty( $actual['data']['registeredStylesheets']['edges'][0]['node']['handle'] );

		// Test fields for first asset.
		global $wp_styles;
		do_action( 'wp_enqueue_scripts' );

		$expected = $wp_styles->registered[ $actual['data']['registeredStylesheets']['nodes'][0]['handle'] ];

		$this->assertEquals( ! empty( $expected->extra['conditional'] ) ? $expected->extra['conditional'] : null, $actual['data']['registeredStylesheets']['nodes'][0]['conditional'] );
		$this->assertEquals( isset( $expected->extra['group'] ) ? $expected->extra['group'] : null, $actual['data']['registeredStylesheets']['nodes'][0]['group'] );
		$this->assertEquals( $expected->handle, $actual['data']['registeredStylesheets']['nodes'][0]['handle'] );
		$this->assertEquals( ! empty( $expected->extra['rtl'] ), $actual['data']['registeredStylesheets']['nodes'][0]['isRtl'] );
		$this->assertEquals( $expected->args ?: 'all', $actual['data']['registeredStylesheets']['nodes'][0]['media'] );
		$this->assertEquals( ! empty( $expected->extra['path'] ) ? $expected->extra['path'] : null, $actual['data']['registeredStylesheets']['nodes'][0]['path'] );
		$this->assertEquals( ! empty( $expected->extra['alt'] ) ? 'alternate stylesheet' : 'stylesheet', $actual['data']['registeredStylesheets']['nodes'][0]['rel'] );
		$this->assertEquals( is_string( $expected->src ) ? $expected->src : null, $actual['data']['registeredStylesheets']['nodes'][0]['src'] );
		$this->assertEquals( ! empty( $expected->extra['suffix'] ) ? $expected->extra['suffix'] : null, $actual['data']['registeredStylesheets']['nodes'][0]['suffix'] );
		$this->assertEquals( ! empty( $expected->extra['title'] ) ? $expected->extra['title'] : null, $actual['data']['registeredStylesheets']['nodes'][0]['title'] );
		$this->assertEquals( $expected->ver ?: $wp_styles->default_version, $actual['data']['registeredStylesheets']['nodes'][0]['version'] );

		// Store for use by $expected.
		$wp_query = $actual['data']['registeredStylesheets'];

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
		$this->assertEquals( false, $actual['data']['registeredStylesheets']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['registeredStylesheets']['pageInfo']['hasNextPage'] );

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
		$variables['after'] = $actual['data']['registeredStylesheets']['pageInfo']['endCursor'];

		// Run the GraphQL Query
		$expected          = $wp_query;
		$expected['edges'] = array_slice( $expected['edges'], 2, 2, false );
		$expected['nodes'] = array_slice( $expected['nodes'], 2, 2, false );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['registeredStylesheets']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['registeredStylesheets']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results.
		 */

		// Set the variables to use in the GraphQL query.
		// There are hundreds of stylesheets, so lets get a good end cursor.
		$actual = $this->graphql(
			[
				'query'     => $this->getQuery(),
				'variables' => [
					'last' => 3,
				],
			]
		);
		$this->assertResponseIsValid( $actual );
		$this->assertNotEmpty( $actual['data']['registeredStylesheets']['edges'][0]['node']['handle'] );
		$variables['after'] = $actual['data']['registeredStylesheets']['pageInfo']['startCursor'];

		// Run the GraphQL Query
		$expected          = $actual['data']['registeredStylesheets'];
		$expected['edges'] = array_slice( $expected['edges'], 1, 2, false );
		$expected['nodes'] = array_slice( $expected['nodes'], 1, 2, false );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['registeredStylesheets']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $actual['data']['registeredStylesheets']['pageInfo']['hasNextPage'] );
	}

	public function testBackwardPagination() {
		wp_set_current_user( $this->admin );
		$query = $this->getQuery();

		// The list of registeredStylesheets might change, so we'll reuse this to check late.
		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'last' => 6,
				],
			]
		);

		// Confirm it's valid.
		$this->assertResponseIsValid( $actual );
		$this->assertNotEmpty( $actual['data']['registeredStylesheets']['edges'][0]['node']['handle'] );

		// Store for use by $expected.
		$wp_query = $actual['data']['registeredStylesheets'];

		/**
		 * Test the first two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables = [
			'last' => 2,
		];

		// Run the GraphQL Query
		$expected          = $wp_query;
		$expected['edges'] = array_slice( $expected['edges'], 4, null, false );
		$expected['nodes'] = array_slice( $expected['nodes'], 4, null, false );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['registeredStylesheets']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $actual['data']['registeredStylesheets']['pageInfo']['hasNextPage'] );

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
		$variables['before'] = $actual['data']['registeredStylesheets']['pageInfo']['startCursor'];

		// Run the GraphQL Query
		$expected          = $wp_query;
		$expected['edges'] = array_slice( $expected['edges'], 2, 2, false );
		$expected['nodes'] = array_slice( $expected['nodes'], 2, 2, false );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['registeredStylesheets']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['registeredStylesheets']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results.
		 */

		// Set the variables to use in the GraphQL query.
		// There are hundreds of scripts, so lets get a good start cursor.
		$actual = $this->graphql(
			[
				'query'     => $this->getQuery(),
				'variables' => [
					'first' => 3,
				],
			]
		);

		$this->assertResponseIsValid( $actual );
		$this->assertNotEmpty( $actual['data']['registeredStylesheets']['edges'][0]['node']['handle'] );

		$variables['before'] = $actual['data']['registeredStylesheets']['pageInfo']['endCursor'];

		// Run the GraphQL Query
		$expected          = $actual['data']['registeredStylesheets'];
		$expected['edges'] = array_slice( $expected['edges'], 0, 2, false );
		$expected['nodes'] = array_slice( $expected['nodes'], 0, 2, false );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( false, $actual['data']['registeredStylesheets']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['registeredStylesheets']['pageInfo']['hasNextPage'] );
	}

	public function testQueryWithFirstAndLast() {
		wp_set_current_user( $this->admin );

		$query = $this->getQuery();

		// The list of registeredStylesheets might change, so we'll reuse this to check late.
		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'first' => 100,
				],
			]
		);

		$after_cursor  = $actual['data']['registeredStylesheets']['edges'][0]['cursor'];
		$before_cursor = $actual['data']['registeredStylesheets']['edges'][2]['cursor'];

		// Get 5 items, but between the bounds of a before and after cursor.
		$variables = [
			'first'  => 5,
			'after'  => $after_cursor,
			'before' => $before_cursor,
		];

		$expected = $actual['data']['registeredStylesheets']['nodes'][1];
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertSame( $expected, $actual['data']['registeredStylesheets']['nodes'][0] );

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
		$this->assertSame( $expected, $actual['data']['registeredStylesheets']['nodes'][0] );
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

		$this->assertEquals( 2, count( $actual['data']['registeredStylesheets']['edges'] ) );

		$first_plugin_handle  = $expected['nodes'][0]['handle'];
		$second_plugin_handle = $expected['nodes'][1]['handle'];

		$start_cursor = $this->toRelayId( 'arrayconnection', $first_plugin_handle );
		$end_cursor   = $this->toRelayId( 'arrayconnection', $second_plugin_handle );

		$this->assertEquals( $first_plugin_handle, $actual['data']['registeredStylesheets']['edges'][0]['node']['handle'] );
		$this->assertEquals( $first_plugin_handle, $actual['data']['registeredStylesheets']['nodes'][0]['handle'] );
		$this->assertEquals( $start_cursor, $actual['data']['registeredStylesheets']['edges'][0]['cursor'] );
		$this->assertEquals( $second_plugin_handle, $actual['data']['registeredStylesheets']['edges'][1]['node']['handle'] );
		$this->assertEquals( $second_plugin_handle, $actual['data']['registeredStylesheets']['nodes'][1]['handle'] );
		$this->assertEquals( $end_cursor, $actual['data']['registeredStylesheets']['edges'][1]['cursor'] );
		$this->assertEquals( $start_cursor, $actual['data']['registeredStylesheets']['pageInfo']['startCursor'] );
		$this->assertEquals( $end_cursor, $actual['data']['registeredStylesheets']['pageInfo']['endCursor'] );
	}
}
