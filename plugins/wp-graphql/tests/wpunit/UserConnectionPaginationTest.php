<?php

class UserConnectionPaginationTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $user_ids;
	public $admin;

	public function setUp(): void {
		parent::setUp();
		$this->delete_users();
		$this->admin    = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		$this->user_ids = $this->create_users( 5 );
		WPGraphQL::clear_schema();
	}

	public function tearDown(): void {
		$this->delete_users();
		parent::tearDown();
	}

	/**
	 * Deletes all users that were created using create_users()
	 */
	public function delete_users() {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}users WHERE ID <> %d",
				[ 0 ]
			)
		);
	}

	/**
	 * Creates several users for use in cursor query tests
	 *
	 * @param   int $count Number of users to create.
	 *
	 * @return array
	 */
	public function create_users( $count = 6 ) {

		// Create users
		$created_users = [];
		for ( $i = 1; $i <= $count; $i++ ) {
			$created_users[] = $this->factory()->user->create(
				[
					'role' => 'editor',
				]
			);
		}

		return $created_users;
	}

	public function usersQuery( $variables ) {

		$query = '
			query getUsers($first: Int, $after: String, $last: Int, $before: String, $where: RootQueryToUserConnectionWhereArgs ) {
				users(first: $first, last: $last, before: $before, after: $after, where: $where) {
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
							databaseId
							username
						}
					}
					nodes {
						databaseId
						username
					}
				}
			}
		';

		return $this->graphql(
			[
				'query'     => $query,
				'variables' => $variables,
			]
		);
	}

	public function forwardPagination( $graphql_args = [], $query_args = [] ) {
		wp_set_current_user( $this->admin );

		/**
		 * Test the first two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables = array_merge(
			[
				'first' => 2,
			],
			$graphql_args
		);

		// Set the variables to use in the WP query.
		$wp_variables = array_merge(
			[
				'number'  => 2,
				'offset'  => 0,
				'fields'  => 'ids',
				'order'   => 'ASC',
				'orderby' => 'name',
			],
			$query_args
		);

		// Run the GraphQL Query
		$expected = ( new WP_User_Query( $wp_variables ) )->get_results();

		$actual = $this->usersQuery( $variables );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( false, $actual['data']['users']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['users']['pageInfo']['hasNextPage'] );

		/**
		 * Test with empty offset.
		 */
		$variables['after'] = '';
		$expected           = $actual;

		$actual = $this->usersQuery( $variables );
		$this->assertEqualSets( $expected, $actual );

		/**
		 * Test the next two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['after'] = $actual['data']['users']['pageInfo']['endCursor'];

		// Set the variables to use in the WP query.
		$wp_variables['offset'] = 2;

		// Run the GraphQL Query
		$expected = ( new WP_User_Query( $wp_variables ) )->get_results();
		$actual   = $this->usersQuery( $variables );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['users']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['users']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['after'] = $actual['data']['users']['pageInfo']['endCursor'];

		// Set the variables to use in the WP query.
		$wp_variables['offset'] = 4;

		// Run the GraphQL Query
		$expected = ( new WP_User_Query( $wp_variables ) )->get_results();
		$actual   = $this->usersQuery( $variables );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['users']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $actual['data']['users']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results are equal to `last:2`.
		 */
		$variables = array_merge(
			[
				'last' => 2,
			],
			$graphql_args
		);
		unset( $variables['first'] );

		$expected = $actual;

		$actual = $this->usersQuery( $variables );
		$this->assertEqualSets( $expected, $actual );
	}

	public function backwardPagination( $graphql_args = [], $query_args = [] ) {
		wp_set_current_user( $this->admin );

		/**
		 * Test the first two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables = array_merge(
			[
				'last' => 2,
			],
			$graphql_args
		);

		// Set the variables to use in the WP query.
		$wp_variables = array_merge(
			[
				'number'  => 2,
				'offset'  => 0,
				'fields'  => 'ids',
				'order'   => 'DESC',
				'orderby' => 'name',
			],
			$query_args
		);

		// Run the GraphQL Query
		$expected = ( new WP_User_Query( $wp_variables ) )->get_results();
		$expected = array_reverse( $expected );

		$actual = $this->usersQuery( $variables );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['users']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $actual['data']['users']['pageInfo']['hasNextPage'] );

		/**
		 * Test with empty offset.
		 */
		$variables['before'] = '';
		$expected            = $actual;

		$actual = $this->usersQuery( $variables );
		$this->assertEqualSets( $expected, $actual );

		/**
		 * Test the next two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['before'] = $actual['data']['users']['pageInfo']['startCursor'];

		// Set the variables to use in the WP query.
		$wp_variables['offset'] = 2;

		// Run the GraphQL Query
		$expected = ( new WP_User_Query( $wp_variables ) )->get_results();
		$expected = array_reverse( $expected );
		$actual   = $this->usersQuery( $variables );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['users']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['users']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['before'] = $actual['data']['users']['pageInfo']['startCursor'];

		// Set the variables to use in the WP query.
		$wp_variables['offset'] = 4;

		// Run the GraphQL Query
		$expected = ( new WP_User_Query( $wp_variables ) )->get_results();
		$expected = array_reverse( $expected );
		$actual   = $this->usersQuery( $variables );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( false, $actual['data']['users']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['users']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results are equal to `first:2`.
		 */
		$variables = array_merge(
			[
				'first' => 2,
			],
			$graphql_args
		);
		unset( $variables['last'] );

		$expected = $actual;

		$actual = $this->usersQuery( $variables );
		$this->assertEqualSets( $expected, $actual );
	}

	public function testForwardPaginationOrderedByDefault() {
		$this->forwardPagination();
	}
	public function testBackwardPaginationOrderedByDefault() {
		$this->backwardPagination();
	}

	public function testForwardPaginationOrderedByLogin() {
		// Set the variables to use in the GraphQL query.
		$variables = [
			'first' => 2,
			'where' => [
				'orderby' => [
					[
						'field' => 'LOGIN',
						'order' => 'DESC',
					],
				],
			],
		];

		// Set the variables to use in the WP query.
		$wp_variables = [
			'number'  => 2,
			'offset'  => 0,
			'fields'  => 'ids',
			'orderby' => 'login',
			'order'   => 'DESC',
		];

		$this->forwardPagination( $variables, $wp_variables );
	}

	public function testBackwardPaginationOrderedByLogin() {
		// Set the variables to use in the GraphQL query.
		$variables = [
			'last'  => 2,
			'where' => [
				'orderby' => [
					[
						'field' => 'LOGIN',
						'order' => 'DESC',
					],
				],
			],
		];

		// Set the variables to use in the WP query.
		$wp_variables = [
			'number'  => 2,
			'offset'  => 0,
			'fields'  => 'ids',
			'orderby' => 'login',
			'order'   => 'ASC',
		];

		$this->backwardPagination( $variables, $wp_variables );
	}

	public function testForwardPaginationOrderedByEmail() {
		// Set the variables to use in the GraphQL query.
		$variables = [
			'first' => 2,
			'where' => [
				'orderby' => [
					[
						'field' => 'EMAIL',
						'order' => 'DESC',
					],
				],
			],
		];

		// Set the variables to use in the WP query.
		$wp_variables = [
			'number'  => 2,
			'offset'  => 0,
			'fields'  => 'ids',
			'orderby' => 'email',
			'order'   => 'DESC',
		];

		$this->forwardPagination( $variables, $wp_variables );
	}

	public function testBackwardPaginationOrderedByEmail() {
		// Set the variables to use in the GraphQL query.
		$variables = [
			'last'  => 2,
			'where' => [
				'orderby' => [
					[
						'field' => 'EMAIL',
						'order' => 'DESC',
					],
				],
			],
		];

		// Set the variables to use in the WP query.
		$wp_variables = [
			'number'  => 2,
			'offset'  => 0,
			'fields'  => 'ids',
			'orderby' => 'email',
			'order'   => 'ASC',
		];

		$this->backwardPagination( $variables, $wp_variables );
	}

	public function testForwardPaginationOrderedByEmailAscending() {
		// Set the variables to use in the GraphQL query.
		$variables = [
			'first' => 2,
			'where' => [
				'orderby' => [
					[
						'field' => 'EMAIL',
						'order' => 'ASC',
					],
				],
			],
		];

		// Set the variables to use in the WP query.
		$wp_variables = [
			'number'  => 2,
			'offset'  => 0,
			'fields'  => 'ids',
			'orderby' => 'email',
			'order'   => 'ASC',
		];

		$this->forwardPagination( $variables, $wp_variables );
	}

	public function testBackwardPaginationOrderedByEmailAscending() {
		// Set the variables to use in the GraphQL query.
		$variables = [
			'last'  => 2,
			'where' => [
				'orderby' => [
					[
						'field' => 'EMAIL',
						'order' => 'ASC',
					],
				],
			],
		];

		// Set the variables to use in the WP query.
		$wp_variables = [
			'number'  => 2,
			'offset'  => 0,
			'fields'  => 'ids',
			'orderby' => 'email',
			'order'   => 'DESC',
		];

		$this->backwardPagination( $variables, $wp_variables );
	}

	public function testQueryWithFirstAndLast() {
		wp_set_current_user( $this->admin );

		/**
		 * Test `first`.
		 */
		$actual = $this->usersQuery( [ 'first' => 5 ] );

		$after_cursor  = $actual['data']['users']['edges'][1]['cursor'];
		$before_cursor = $actual['data']['users']['edges'][3]['cursor'];

		// Get 5 items, but between the bounds of a before and after cursor.
		$variables = [
			'first'  => 5,
			'after'  => $after_cursor,
			'before' => $before_cursor,
		];

		$expected = $actual['data']['users']['nodes'][2];
		$actual   = $this->usersQuery( $variables );

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['users']['nodes'][0] );

		/**
		 * Test `last`.
		 */
		$variables['last'] = 5;

		// Using first and last should throw an error.
		$actual = $this->usersQuery( $variables );

		$this->assertArrayHasKey( 'errors', $actual );

		unset( $variables['first'] );

		// Get 5 items, but between the bounds of a before and after cursor.
		$actual = $this->usersQuery( $variables );

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( $expected, $actual['data']['users']['nodes'][0] );
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

		$this->assertEquals( 2, count( $actual['data']['users']['edges'] ) );
		$expected = array_values( $expected );

		$first_user_id  = $expected[0];
		$second_user_id = $expected[1];

		$start_cursor = $this->toRelayId( 'arrayconnection', $first_user_id );
		$end_cursor   = $this->toRelayId( 'arrayconnection', $second_user_id );

		$this->assertEquals( $first_user_id, $actual['data']['users']['edges'][0]['node']['databaseId'] );
		$this->assertEquals( $first_user_id, $actual['data']['users']['nodes'][0]['databaseId'] );
		$this->assertEquals( $start_cursor, $actual['data']['users']['edges'][0]['cursor'] );
		$this->assertEquals( $second_user_id, $actual['data']['users']['edges'][1]['node']['databaseId'] );
		$this->assertEquals( $second_user_id, $actual['data']['users']['nodes'][1]['databaseId'] );
		$this->assertEquals( $end_cursor, $actual['data']['users']['edges'][1]['cursor'] );
		$this->assertEquals( $start_cursor, $actual['data']['users']['pageInfo']['startCursor'] );
		$this->assertEquals( $end_cursor, $actual['data']['users']['pageInfo']['endCursor'] );
	}
}
