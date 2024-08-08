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
						handle
						isRtl
						media
						path
						rel
						src
						suffix
						title
						version
						group
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
		$this->assertQuerySuccessful(
			$actual,
			[ $this->expectedField( 'registeredStylesheets.edges.0.node.handle', static::NOT_NULL ) ]
		);

		// Test fields for first asset.
		global $wp_styles;
		$expected_handle = $actual['data']['registeredStylesheets']['nodes'][0]['handle'];
		$expected = $wp_styles->registered[ $expected_handle ];

		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedNode(
					'registeredStylesheets.nodes',
					[
						$this->expectedField( 'conditional', ! empty( $expected->extra['conditional'] ) ? $expected->extra['conditional'] : static::IS_NULL ),
						$this->expectedField( 'handle', $expected->handle ),
						$this->expectedField( 'isRtl', ! empty( $expected->extra['rtl'] ) ? static::NOT_FALSY : static::IS_FALSY ),
						$this->expectedField( 'media', $expected->args ?: 'all' ),
						$this->expectedField( 'path', ! empty( $expected->extra['path'] ) ? $expected->extra['path'] : static::IS_NULL ),
						$this->expectedField( 'rel', ! empty( $expected->extra['alt'] ) ? 'alternate stylesheet' : 'stylesheet' ),
						$this->expectedField( 'src', is_string( $expected->src ) ? $expected->src : static::IS_NULL ),
						$this->expectedField( 'suffix', ! empty( $expected->extra['suffix'] ) ? $expected->extra['suffix'] : static::IS_NULL ),
						$this->expectedField( 'title', ! empty( $expected->extra['title'] ) ? $expected->extra['title'] : static::IS_NULL ),
						$this->expectedField( 'version', $expected->ver ?: $wp_styles->default_version ),
						$this->expectedField( 'group', $expected->extra['group'] ?? 0 ),
					],
					0
				)
			]
		);

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
		$first_plugin_handle  = $this->lodashGet( $expected, 'nodes.0.handle' );
		$second_plugin_handle = $this->lodashGet( $expected, 'nodes.1.handle' );
		$start_cursor = $this->toRelayId( 'arrayconnection', $first_plugin_handle );
		$end_cursor   = $this->toRelayId( 'arrayconnection', $second_plugin_handle );
		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'registeredStylesheets.pageInfo.startCursor', $start_cursor ),
				$this->expectedField( 'registeredStylesheets.pageInfo.endCursor', $end_cursor ),
				$this->expectedNode(
					'registeredStylesheets.edges',
					[
						$this->expectedField( 'cursor', $start_cursor ),
						$this->expectedField( 'node.handle', $first_plugin_handle ),
					],
					0
				),
				$this->expectedNode(
					'registeredStylesheets.edges',
					[
						$this->expectedField( 'cursor', $end_cursor ),
						$this->expectedField( 'node.handle', $second_plugin_handle ),
					],
					1
				),
				$this->not()->expectedField( 'registeredStylesheets.edges.2', static::NOT_FALSY ),
				$this->expectedNode(
					'registeredStylesheets.nodes',
					[
						$this->expectedField( 'handle', $first_plugin_handle ),
					],
					0
				),
				$this->expectedNode(
					'registeredStylesheets.nodes',
					[
						$this->expectedField( 'handle', $second_plugin_handle ),
					],
					1
				),
				$this->not()->expectedField( 'registeredStylesheets.nodes.2', static::NOT_FALSY )
			]
		);
	}
}
