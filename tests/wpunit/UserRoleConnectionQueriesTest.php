<?php

class UserRoleConnectionQueriesTest extends \Codeception\TestCase\WPTestCase {

	public function setUp() {
		parent::setUp();
	}

	public function tearDown() {
		parent::tearDown();
	}

	/**
	 * Test that the user role query works as expected
	 */
	public function testUserRoleConnectionQuery() {

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

			$data['id'] = \GraphQLRelay\Relay::toGlobalId( 'role', $role_name );
			$data['capabilities'] = array_keys( $data['capabilities'], true, true );
			$nodes[]['node'] = $data;

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
