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

			$node = \WPGraphQL\Data\DataSource::resolve_user_role( $role_name );
			$node['id'] = base64_encode( 'role:' . $role_name );
			$node['capabilities'] = array_keys( $data['capabilities'] );

			$nodes[]['node'] = $node;


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
