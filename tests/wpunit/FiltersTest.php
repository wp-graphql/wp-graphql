<?php

class FiltersTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 *
	 */
	public $filter_values = [];

	public function setUp(): void {
		$this->filter_values = [];
		parent::setUp();
		$this->clearSchema();
	}

	public function tearDown(): void {
		$this->filter_values = [];
		$this->clearSchema();
		parent::tearDown();
	}

	public function testFilterGraphqlRequestResults() {

		add_filter(
			'graphql_request_results',
			function ( $response, $schema, $operation, $query, $variables, $request ) {
				$this->filter_values = [
					'query'     => $query,
					'variables' => $variables,
				];
			},
			10,
			6
		);

		$request = [
			'query'     => 'query GetPosts($first:Int){posts(first:$first){nodes{id,title}}}',
			'variables' => [ 'first' => 1 ],
		];

		$actual = graphql( $request );

		codecept_debug( $this->filter_values, $request );

		$this->assertSame( $this->filter_values, $request );
	}

	public function testFilterGraphqlRequestResultsForBatchQuery() {

		add_filter(
			'graphql_request_results',
			function ( $response, $schema, $operation, $query, $variables, $request ) {

				$this->filter_values[] = [
					'query'     => $query,
					'variables' => $variables,
				];

				return $response;
			},
			10,
			6
		);

		$request = [
			[
				'query'     => 'query GetPosts($first:Int){posts(first:$first){nodes{id,title}}}',
				'variables' => [ 'first' => 1 ],
			],
			[
				'query'     => 'query GetPosts($first:Int){posts(first:$first){nodes{id}}}',
				'variables' => [ 'first' => 2 ],
			],
		];

		$actual = graphql( $request );

		codecept_debug( $this->filter_values );
		codecept_debug( $request );

		$this->assertSame( $request, $this->filter_values );
	}

	/**
	 * @see: https://github.com/wp-graphql/wp-graphql/issues/2048
	 */
	public function testFilterConnectionQueryArgsForUserRoleQueriesDoesntReturnError() {

		$admin = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);

		$this->factory()->user->create(
			[
				'role' => 'subscriber',
			]
		);

		$this->factory()->post->create(
			[
				'post_status' => 'publish',
				'post_author' => $admin,
				'post_title'  => 'Test Filters',
			]
		);

		set_current_user( $admin );

		$query = '
		{
			users {
				nodes {
					roles {
						nodes {
							displayName
							id
						}
					}
				}
			}
		}
		';

		// Add a filter to the connection query args
		// This should not throw an error because it's returning the $query_args untouched
		add_filter( 'graphql_connection_query_args', 'my_custom_filter', 10, 2 );
		function my_custom_filter( array $query_args, \WPGraphQL\Data\Connection\AbstractConnectionResolver $resolver ) {
			return $query_args;
		}

		$actual = graphql(
			[
				'query' => $query,
			]
		);

		codecept_debug( $actual );

		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'users.nodes', self::NOT_NULL ),
			]
		);
	}

	public function testFilterWPConnectionTypeConfigDoesntReturnError() {
		// Add a filter to the connection type config
		// This should not throw an error because it's returning the $config untouched
		add_filter(
			'graphql_wp_connection_type_config',
			function ( $config, $wp_connection_type ) {
				// Ensure the connection instance is passed correctly.
				$this->assertInstanceOf( '\WPGraphQL\Type\WPConnectionType', $wp_connection_type );

				return $config;
			},
			10,
			2
		);

		$query = '
		{
			posts {
				nodes {
					id
					title
				}
			}
		}
		';

		$actual = graphql(
			[
				'query' => $query,
			]
		);

		codecept_debug( $actual );

		$this->assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'posts.nodes', self::NOT_NULL ),
			]
		);
	}

		/**
	 * Test that the graphql_root_value filter has access to request params
	 * and can modify the root value based on query context.
	 */
	public function testFilterGraphqlRootValueHasAccessToRequestParams() {
		$filter_called = false;
		$captured_params = null;
		$captured_request = null;

		// Add a filter to capture and modify the root value based on request params
		add_filter(
			'graphql_root_value',
			static function ( $root_value, $request ) use ( &$filter_called, &$captured_params, &$captured_request ) {
				$filter_called = true;
				$captured_request = $request;
				$captured_params = $request->get_params();

				codecept_debug( 'Filter called! Root value:', $root_value );
				codecept_debug( 'Request object type:', get_class( $request ) );
				codecept_debug( 'Params from get_params():', $captured_params );

				return $root_value;
			},
			10,
			2
		);

		$query = '{ posts { nodes { id } } }';

		$request = [
			'query' => $query,
		];

		$actual = graphql( $request );

		codecept_debug( 'Filter was called:', $filter_called ? 'YES' : 'NO' );
		codecept_debug( 'GraphQL Response:', $actual );

		// If there are errors, show them
		if ( isset( $actual['errors'] ) ) {
			codecept_debug( 'GraphQL Errors:', $actual['errors'] );
		}

		// The main assertion: the filter should have been called
		$this->assertTrue( $filter_called, 'The graphql_root_value filter should be called' );

		if ( $filter_called ) {
			$this->assertNotNull( $captured_request, 'Request should be passed to graphql_root_value filter' );
			$this->assertNotNull( $captured_params, 'Request params should be available in graphql_root_value filter' );

			if ( $captured_params ) {
				$this->assertInstanceOf( '\GraphQL\Server\OperationParams', $captured_params, 'Params should be OperationParams instance' );
				$this->assertEquals( $query, $captured_params->query, 'Query should match what was sent' );
			}
		}
	}

		/**
	 * Test that the graphql_root_value filter works correctly with batch requests
	 */
	public function testFilterGraphqlRootValueHasAccessToRequestParamsForBatchQuery() {
		$filter_call_count = 0;
		$captured_requests = [];
		$captured_params = [];

		// Add a filter to capture params for each request in the batch
		add_filter(
			'graphql_root_value',
			static function ( $root_value, $request ) use ( &$filter_call_count, &$captured_requests, &$captured_params ) {
				$filter_call_count++;
				$captured_requests[] = $request;
				$captured_params[] = $request->get_params();

				codecept_debug( "Filter called #{$filter_call_count}" );
				codecept_debug( 'Request object type:', get_class( $request ) );
				codecept_debug( 'Params from get_params():', $request->get_params() );

				return $root_value;
			},
			10,
			2
		);

		$request = [
			[
				'query' => '{ posts { nodes { id } } }',
			],
			[
				'query' => '{ users { nodes { id } } }',
			],
		];

		$actual = graphql( $request );

		codecept_debug( 'Filter call count:', $filter_call_count );
		codecept_debug( 'Captured Requests Count:', count( $captured_requests ) );
		codecept_debug( 'Captured Params Count:', count( $captured_params ) );
		codecept_debug( 'Batch GraphQL Response:', $actual );

		// Assert that the filter was called for each request in the batch
		$this->assertEquals( 2, $filter_call_count, 'Filter should be called twice for batch query' );
		$this->assertCount( 2, $captured_requests, 'Should capture 2 requests for batch query' );
		$this->assertCount( 2, $captured_params, 'Should capture 2 sets of params for batch query' );

		// Assert that both params are OperationParams instances
		if ( count( $captured_params ) >= 2 ) {
			$this->assertInstanceOf( '\GraphQL\Server\OperationParams', $captured_params[0], 'First params should be OperationParams instance' );
			$this->assertInstanceOf( '\GraphQL\Server\OperationParams', $captured_params[1], 'Second params should be OperationParams instance' );

			// Assert that params contain the expected query information
			$this->assertEquals( $request[0]['query'], $captured_params[0]->query, 'First query should match' );
			$this->assertEquals( $request[1]['query'], $captured_params[1]->query, 'Second query should match' );
		}
	}
}