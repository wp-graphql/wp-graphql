<?php

class UserRoleConnectionQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	private $admin;

	public function setUp(): void {
		parent::setUp();

		add_role(
			'test_role',
			__( 'Test role' ),
			[
				'read'                   => true,
				'delete_posts'           => true,
				'delete_published_posts' => true,
				'edit_posts'             => true,
				'publish_posts'          => true,
				'upload_files'           => true,
				'edit_pages'             => true,
				'edit_published_pages'   => true,
				'publish_pages'          => true,
				'delete_published_pages' => true,
			]
		);

		$this->admin = $this->factory()->user->create( [ 'role' => 'administrator' ] );
	}

	public function tearDown(): void {
		parent::tearDown();
	}


	public function getQuery() {
		return '
			query testUserRoles($first: Int, $after: String, $last: Int, $before: String ) {
				userRoles(first: $first, last: $last, before: $before, after: $after) {
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
							name
						}
					}
					nodes {
						id
						name
					}
				}
			}
		';
	}

	/**
	 * Test that a user with no role returns no roles in the query.
	 */
	public function testUserWithNoRole() {
		wp_set_current_user( $this->admin );

		// Create a user with no role
		$user_with_no_role = $this->factory()->user->create( [ 'role' => false ] );

		// Set the test query
		$query = '
			query GetUser($id: ID!) {
				user(id: $id, idType: DATABASE_ID) {
					roles {
						edges {
							node {
								displayName
							}
						}
					}
				}
			}
        ';

		// Execute the GraphQL Query
		$actual = $this->graphql(
			[
				'query'     => $query,
				'variables' => [
					'id' => (int) $user_with_no_role,
				],
			]
		);

		codecept_debug(
			[
				'$actual' => $actual,
			]
		);

		// Assert that the user with no role returns no roles in the query
		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'user.roles', self::IS_NULL ),
			]
		);

		// cleanup the user that was created
		wp_delete_user( (int) $user_with_no_role );
	}

	/**
	 * Test that the user role query works as expected
	 *
	 * @throws \Exception
	 */
	public function testUserRoleConnectionQuery() {

		wp_set_current_user( $this->admin );

		$query = '
		query {
			userRoles{
				edges {
					node {
						name
						capabilities
						id
						displayName
					}
				}
			}
		}
		';

		$nodes = [];
		$roles = wp_roles();
		foreach ( $roles->roles as $role_name => $data ) {
			$clean_node = [];

			$data['slug']               = $role_name;
			$data['id']                 = $role_name;
			$data['displayName']        = $data['name'];
			$data['name']               = $role_name;
			$node                       = new \WPGraphQL\Model\UserRole( $data );
			$clean_node['id']           = $node->id;
			$clean_node['name']         = $node->name;
			$clean_node['displayName']  = $node->displayName;
			$clean_node['capabilities'] = $node->capabilities;

			$nodes[]['node'] = $clean_node;
		}

		$expected = [
			'userRoles' => [
				'edges' => $nodes,
			],
		];

		$actual = $this->graphql( [ 'query' => $query ] );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $expected, $actual['data'] );
	}

	public function testForwardPagination() {
		wp_set_current_user( $this->admin );
		$query = $this->getQuery();

		// The list of userRoles might change, so we'll reuse this to check late.
		$actual = graphql(
			[
				'query' => $query,
			]
		);

		// Confirm its valid.
		$this->assertResponseIsValid( $actual );
		$this->assertNotEmpty( $actual['data']['userRoles']['edges'][0]['node']['name'] );

		// Store for use by $expected.
		$wp_query = $actual['data']['userRoles'];

		/**
		 * Test the first two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables = [
			'first' => 2,
		];

		// Run the GraphQL Query
		$expected = $wp_query;

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( false, $actual['data']['userRoles']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['userRoles']['pageInfo']['hasNextPage'] );

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
		$variables['after'] = $actual['data']['userRoles']['pageInfo']['endCursor'];

		// Run the GraphQL Query
		$expected          = $wp_query;
		$expected['edges'] = array_slice( $expected['edges'], 2, 2, false );
		$expected['nodes'] = array_slice( $expected['nodes'], 2, 2, false );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['userRoles']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['userRoles']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['after'] = $actual['data']['userRoles']['pageInfo']['endCursor'];

		// Run the GraphQL Query
		$expected          = $wp_query;
		$expected['edges'] = array_slice( $expected['edges'], 4, null, false );
		$expected['nodes'] = array_slice( $expected['nodes'], 4, null, false );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['userRoles']['pageInfo']['hasPreviousPage'] );

		$this->assertEquals( false, $actual['data']['userRoles']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results are equal to `last:2`.
		 */
		$variables = [
			'last' => 2,
		];
		$expected  = $actual;

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual );
	}

	public function testBackwardPagination() {
		wp_set_current_user( $this->admin );
		$query = $this->getQuery();

		// The list of userRoles might change, so we'll reuse this to check late.
		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'last' => 6,
				],
			]
		);

		// Confirm its valid.
		$this->assertResponseIsValid( $actual );
		$this->assertNotEmpty( $actual['data']['userRoles']['edges'][0]['node']['name'] );

		$wp_query = $actual['data']['userRoles'];

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
		$this->assertEquals( true, $actual['data']['userRoles']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( false, $actual['data']['userRoles']['pageInfo']['hasNextPage'] );

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
		$variables['before'] = $actual['data']['userRoles']['pageInfo']['startCursor'];

		// Run the GraphQL Query
		$expected          = $wp_query;
		$expected['edges'] = array_slice( $expected['edges'], 2, 2, false );
		$expected['nodes'] = array_slice( $expected['nodes'], 2, 2, false );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( true, $actual['data']['userRoles']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['userRoles']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results.
		 */

		// Set the variables to use in the GraphQL query.
		$variables['before'] = $actual['data']['userRoles']['pageInfo']['startCursor'];

		// Run the GraphQL Query
		$expected          = $wp_query;
		$expected['edges'] = array_slice( $expected['edges'], 0, 2, false );
		$expected['nodes'] = array_slice( $expected['nodes'], 0, 2, false );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertValidPagination( $expected, $actual );
		$this->assertEquals( false, $actual['data']['userRoles']['pageInfo']['hasPreviousPage'] );
		$this->assertEquals( true, $actual['data']['userRoles']['pageInfo']['hasNextPage'] );

		/**
		 * Test the last two results are equal to `first:2`.
		 */
		$variables = [
			'first' => 2,
		];
		$expected  = $actual;

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual );
	}

	public function testQueryWithFirstAndLast() {
		wp_set_current_user( $this->admin );

		$query = $this->getQuery();

		// The list of userRoles might change, so we'll reuse this to check late.
		$actual = graphql(
			[
				'query'     => $query,
				'variables' => [
					'first' => 100,
				],
			]
		);

		$after_cursor  = $actual['data']['userRoles']['edges'][0]['cursor'];
		$before_cursor = $actual['data']['userRoles']['edges'][2]['cursor'];

		// Get 5 items, but between the bounds of a before and after cursor.
		$variables = [
			'first'  => 5,
			'after'  => $after_cursor,
			'before' => $before_cursor,
		];

		$expected = $actual['data']['userRoles']['nodes'][1];
		$actual   = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertResponseIsValid( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertSame( $expected, $actual['data']['userRoles']['nodes'][0] );

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
		$this->assertSame( $expected, $actual['data']['userRoles']['nodes'][0] );
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

		$this->assertEquals( 2, count( $actual['data']['userRoles']['edges'] ) );

		$first_user_role_name  = $expected['nodes'][0]['name'];
		$second_user_role_name = $expected['nodes'][1]['name'];

		$start_cursor = $this->toRelayId( 'arrayconnection', $first_user_role_name );
		$end_cursor   = $this->toRelayId( 'arrayconnection', $second_user_role_name );

		$this->assertEquals( $first_user_role_name, $actual['data']['userRoles']['edges'][0]['node']['name'] );
		$this->assertEquals( $first_user_role_name, $actual['data']['userRoles']['nodes'][0]['name'] );
		$this->assertEquals( $start_cursor, $actual['data']['userRoles']['edges'][0]['cursor'] );
		$this->assertEquals( $second_user_role_name, $actual['data']['userRoles']['edges'][1]['node']['name'] );
		$this->assertEquals( $second_user_role_name, $actual['data']['userRoles']['nodes'][1]['name'] );
		$this->assertEquals( $end_cursor, $actual['data']['userRoles']['edges'][1]['cursor'] );
		$this->assertEquals( $start_cursor, $actual['data']['userRoles']['pageInfo']['startCursor'] );
		$this->assertEquals( $end_cursor, $actual['data']['userRoles']['pageInfo']['endCursor'] );
	}
}
