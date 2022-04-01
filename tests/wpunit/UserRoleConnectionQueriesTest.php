<?php

class UserRoleConnectionQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	private $admin;

	public function setUp(): void {
		parent::setUp();
		$this->admin = $this->factory()->user->create( [ 'role' => 'administrator' ] );
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Test that the user role query works as expected
	 *
	 * @throws Exception
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

	/**
	 * Tests querying for plugins with pagination args.
	 */
	public function testUserRolesQueryPagination() {
		wp_set_current_user( $this->admin );

		$query = '
			query testUserRoles($first: Int, $after: String, $last: Int, $before: String ) {
				userRoles(first: $first, last: $last, before: $before, after: $after) {
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
			'first'  => null,
			'after'  => null,
			'last'   => null,
			'before' => null,
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertIsValidQueryResponse( $actual );

		$nodes = $actual['data']['userRoles']['nodes'];

		// Get first two userRoles
		$variables['first'] = 2;

		$expected = array_slice( $nodes, 0, $variables['first'], true );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual['data']['userRoles']['nodes'] );

		// Test with empty `after`.
		$variables['after'] = '';
		$actual             = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual['data']['userRoles']['nodes'] );

		// Get last two userRoles
		$variables = [
			'first'  => null,
			'after'  => null,
			'last'   => 2,
			'before' => null,
		];

		$expected = array_slice( $nodes, $variables['last'], null, true );
		$actual   = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual['data']['userRoles']['nodes'] );

		// Test with empty `before`.
		$variables['before'] = '';
		$actual              = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertEqualSets( $expected, $actual['data']['userRoles']['nodes'] );
	}

}
