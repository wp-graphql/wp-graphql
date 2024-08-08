<?php

use WPGraphQL\Type\WPEnumType;

class RegisteredScriptConnectionQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	private $admin;

	public function setUp(): void {
		parent::setUp();
		$this->admin = $this->factory()->user->create( [ 'role' => 'administrator' ] );
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	public function getQuery() {
		return '
			query testRegisteredScripts($first: Int, $after: String, $last: Int, $before: String ) {
				registeredScripts(first: $first, last: $last, before: $before, after: $after) {
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
						after
						before
						conditional
						dependencies {
							handle
						}
						extraData
						handle
						id
						src
						strategy
						group
						location
						version
					}
				}
			}
		';
	}

	public function testForwardPagination() {
		wp_set_current_user( $this->admin );
		$query = $this->getQuery();

		// The list of registeredScripts might change, so we'll reuse this to check late.
		$variables = [ 'first' => 4 ];
		$actual    = $this->graphql( compact( 'query', 'variables' ) );

		// Confirm it's valid.
		$this->assertResponseIsValid( $actual );

		// Test fields for first asset.
		global $wp_scripts;
		$expected_handle = $this->lodashGet( $actual, 'data.registeredScripts.edges.0.node.handle' );
		$expected        = $wp_scripts->registered[ $expected_handle ];

		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'registeredScripts.pageInfo.hasNextPage', true ),
				$this->expectedField( 'registeredScripts.pageInfo.hasPreviousPage', false ),
				$this->expectedEdge(
					'registeredScripts.edges',
					[
						$this->expectedField(
							'node.after',
							! empty( $expected->extra['after'] )
								? ( array_filter( $expected->extra['after'], 'is_string' ) ?: static::IS_NULL )
								: static::IS_NULL
						),
						$this->expectedField(
							'node.before',
							! empty( $expected->extra['before'] )
								? ( array_filter( $expected->extra['before'], 'is_string' ) ?: static::IS_NULL )
								: static::IS_NULL
						),
					],
					0
				),
				$this->expectedNode(
					'registeredScripts.nodes',
					[
						$this->expectedField( 'handle', $expected->handle ),
						$this->expectedField( 'src', $expected->src ),
						$this->expectedField(
							'conditional',
							! empty( $expected->extra['conditional'] )
								? $expected->extra['conditional']
								: static::IS_NULL
						),
						$this->expectedField(
							'strategy',
							! empty( $expected->extra['strategy'] )
								? WPEnumType::get_safe_name( $expected->extra['strategy'] )
								: static::IS_NULL
						),
						$this->expectedField(
							'extraData',
							! empty( $expected->extra['data'] ) ? $expected->extra['data'] : static::IS_NULL
						),
						$this->expectedField( 'version', $expected->ver ?: $wp_scripts->default_version ),
						$this->expectedField( 'group', ! isset( $expected->extra['group'] ) ? 0 : $expected->extra['group'] ),
						$this->expectedField( 'location', ! isset( $expected->extra['group'] ) ? 'HEADER' : 'FOOTER' ),
					],
					0
				),
			]
		);

		// Store for use by $expected.
		$wp_query = $actual['data']['registeredScripts'];

		/**
		 * Test the first two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables = [ 'first' => 2 ];

		// Run the GraphQL Query
		$expected = $wp_query;
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'registeredScripts.pageInfo.hasPreviousPage', false ),
				$this->expectedField( 'registeredScripts.pageInfo.hasNextPage', true ),
			]
		);

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
		$variables['after'] = $this->lodashGet( $actual, 'data.registeredScripts.pageInfo.endCursor' );

		// Run the GraphQL Query
		$expected          = $wp_query;
		$expected['edges'] = array_slice( $expected['edges'], 2, 2, false );
		$expected['nodes'] = array_slice( $expected['nodes'], 2, 2, false );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'registeredScripts.pageInfo.hasPreviousPage', true ),
				$this->expectedField( 'registeredScripts.pageInfo.hasNextPage', true ),
			]
		);

		/**
		 * Test the last two results.
		 */

		// Set the variables to use in the GraphQL query.
		// There are hundreds of scripts, so lets get a good end cursor.
		$actual = $this->graphql(
			[
				'query'     => $this->getQuery(),
				'variables' => [
					'last' => 3,
				],
			]
		);
		$this->assertQuerySuccessful(
			$actual,
			[ $this->expectedField( 'registeredScripts.edges.0.node.handle', static::NOT_NULL ) ]
		);

		$variables['after'] = $this->lodashGet( $actual, 'data.registeredScripts.pageInfo.startCursor' );

		// Run the GraphQL Query
		$expected          = $actual['data']['registeredScripts'];
		$expected['edges'] = array_slice( $expected['edges'], 1, 2, false );
		$expected['nodes'] = array_slice( $expected['nodes'], 1, 2, false );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'registeredScripts.pageInfo.hasPreviousPage', true ),
				$this->expectedField( 'registeredScripts.pageInfo.hasNextPage', false ),
			]
		);
	}

	public function testBackwardPagination() {
		wp_set_current_user( $this->admin );
		$query = $this->getQuery();

		// The list of registeredScripts might change, so we'll reuse this to check late.
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'last' => 6,
				],
			]
		);

		// Confirm it's valid.
		$this->assertQuerySuccessful(
			$actual,
			[ $this->expectedField( 'registeredScripts.edges.0.node.handle', static::NOT_NULL ) ]
		);

		// Store for use by $expected.
		$wp_query = $actual['data']['registeredScripts'];

		/**
		 * Test the first two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables = [ 'last' => 2 ];

		// Run the GraphQL Query
		$expected          = $wp_query;
		$expected['edges'] = array_slice( $expected['edges'], 4, null, false );
		$expected['nodes'] = array_slice( $expected['nodes'], 4, null, false );

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['registeredScripts']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $actual['data']['registeredScripts']['pageInfo']['hasNextPage'] );

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
		$variables['before'] = $actual['data']['registeredScripts']['pageInfo']['startCursor'];

		// Run the GraphQL Query
		$expected          = $wp_query;
		$expected['edges'] = array_slice( $expected['edges'], 2, 2, false );
		$expected['nodes'] = array_slice( $expected['nodes'], 2, 2, false );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['registeredScripts']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['registeredScripts']['pageInfo']['hasNextPage'] );

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
		$this->assertNotEmpty( $actual['data']['registeredScripts']['edges'][0]['node']['handle'] );

		$variables['before'] = $actual['data']['registeredScripts']['pageInfo']['endCursor'];

		// Run the GraphQL Query
		$expected          = $actual['data']['registeredScripts'];
		$expected['edges'] = array_slice( $expected['edges'], 0, 2, false );
		$expected['nodes'] = array_slice( $expected['nodes'], 0, 2, false );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( false, $actual['data']['registeredScripts']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['registeredScripts']['pageInfo']['hasNextPage'] );
	}

	public function testQueryWithFirstAndLast() {
		wp_set_current_user( $this->admin );

		$query = $this->getQuery();

		// The list of registeredScripts might change, so we'll reuse this to check late.
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'first' => 100,
				],
			]
		);

		$after_cursor  = $this->lodashGet( $actual, 'data.registeredScripts.edges.0.cursor' );
		$before_cursor = $this->lodashGet( $actual, 'data.registeredScripts.edges.2.cursor' );

		// Get 5 items, but between the bounds of a before and after cursor.
		$variables = [
			'first'  => 5,
			'after'  => $after_cursor,
			'before' => $before_cursor,
		];

		$expected = $this->lodashGet( $actual, 'data.registeredScripts.nodes.1' );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertQuerySuccessful(
			$actual,
			[ $this->expectedField( 'registeredScripts.nodes.0', $expected ) ]
		);

		/**
		 * Test `last`.
		 */
		$variables['last'] = 5;

		// Using first and last should throw an error.
		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertQueryError( $actual );

		unset( $variables['first'] );

		// Get 5 items, but between the bounds of a before and after cursor.
		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertQuerySuccessful(
			$actual,
			[ $this->expectedField( 'registeredScripts.nodes.0', $expected ) ]
		);
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
				$this->expectedField( 'registeredScripts.pageInfo.startCursor', $start_cursor ),
				$this->expectedField( 'registeredScripts.pageInfo.endCursor', $end_cursor ),
				$this->expectedNode(
					'registeredScripts.edges',
					[
						$this->expectedField( 'cursor', $start_cursor ),
						$this->expectedField( 'node.handle', $first_plugin_handle ),
					],
					0
				),
				$this->expectedNode(
					'registeredScripts.edges',
					[
						$this->expectedField( 'cursor', $end_cursor ),
						$this->expectedField( 'node.handle', $second_plugin_handle ),
					],
					1
				),
				$this->not()->expectedField( 'registeredScripts.edges.2', static::NOT_FALSY ),
				$this->expectedNode(
					'registeredScripts.nodes',
					[
						$this->expectedField( 'handle', $first_plugin_handle ),
					],
					0
				),
				$this->expectedNode(
					'registeredScripts.nodes',
					[
						$this->expectedField( 'handle', $second_plugin_handle ),
					],
					1
				),
				$this->not()->expectedField( 'registeredScripts.nodes.2', static::NOT_FALSY )
			]
		);
	}
}
