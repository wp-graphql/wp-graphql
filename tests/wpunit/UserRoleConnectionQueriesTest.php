<?php

class UserRoleConnectionQueriesTest extends \Codeception\TestCase\WPTestCase {

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

		$query = "
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
		";

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

		$actual = do_graphql_request( $query );
		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $expected, $actual['data'] );

	}

}
