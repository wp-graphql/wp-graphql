<?php

class UserRoleConnectionQueriesTest extends \Codeception\TestCase\WPTestCase {

	private $admin;

	public function setUp() {
		parent::setUp();
		$this->admin = $this->factory->user->create( [ 'role' => 'administrator' ] );
	}

	public function tearDown() {
		parent::tearDown();
	}

	/**
	 * Test that the user role query works as expected
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
					}
				}
			}
		}
		";

		$nodes = [];
		$roles = wp_roles();
		foreach ( $roles->roles as $role_name => $data ) {

			$clean_node = [];
			$node = \WPGraphQL\Data\DataSource::resolve_user_role( $role_name );
			$clean_node['id'] = $node->id;
			$clean_node['name'] = $node->name;
			$clean_node['capabilities'] = $node->capabilities;

			$nodes[]['node'] = $clean_node;

		}

		$expected = [
			'data' => [
				'userRoles' => [
					'edges' => $nodes,
				],
			],
		];

		$actual = do_graphql_request( $query );

		$this->assertEquals( $expected, $actual );

	}

}
