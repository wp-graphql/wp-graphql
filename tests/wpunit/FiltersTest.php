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
}
