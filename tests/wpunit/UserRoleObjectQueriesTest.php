<?php

class UserRoleObjectQueriesTest extends \Codeception\TestCase\WPTestCase {

	private $admin;

	public function setUp() {
		parent::setUp();
		$this->admin = $this->factory->user->create( [ 'role' => 'administrator' ] );
	}

	public function tearDown() {
		parent::tearDown();
	}

	/**
	 * Test that a user is properly returned
	 */
	public function testUserRoleQuery() {

		wp_set_current_user( $this->admin );
		$role_to_test = 'administrator';

		$global_id = \GraphQLRelay\Relay::toGlobalId( 'role', $role_to_test );
		$role_obj = get_role( $role_to_test );

		$query = "
		query {
			userRole(id: \"$global_id\"){
				id
				name
				capabilities
			}
		}
		";

		$expected = [
			'data' => [
				'userRole' => [
					'id' => $global_id,
					'name' => $role_to_test,
					'capabilities' => array_keys( $role_obj->capabilities, true, true ),
				],
			],
		];

		$actual = do_graphql_request( $query );

		$this->assertEquals( $expected, $actual );

	}

	/**
	 * Test for a failure when you try to search for a user role that doesn't exist
	 */
	public function testUserRoleQueryNonExistent() {

		wp_set_current_user( $this->admin );
		$role_to_test = 'norole';
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'role', $role_to_test );

		$query = "
		query {
			userRole(id: \"$global_id\"){
				id
				name
				capabilities
			}
		}
		";

		$expected = sprintf( 'No user role was found with the name %s', $role_to_test );
		$actual = do_graphql_request( $query );

		$this->assertEquals( $expected, $actual['errors'][0]['message'] );
		$this->assertNull( $actual['data']['userRole'] );

	}

}