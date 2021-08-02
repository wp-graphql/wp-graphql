<?php

class UserRoleObjectQueriesTest extends \Codeception\TestCase\WPTestCase {

	private $admin;
	public $request;

	public function setUp(): void {
		parent::setUp();
		$this->admin = $this->factory->user->create( [ 'role' => 'administrator' ] );
	}

	/**
	 * @param $request_data
	 *
	 * @return array
	 * @throws Exception
	 */
	public function request( $request_data ) {
		$request = new \WPGraphQL\Request( $request_data );
		return $request->execute();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Test for a failure when you try to search for a user role that doesn't exist
	 *
	 * @throws Exception
	 */
	public function testUserRoleQueryNonExistent() {

		wp_set_current_user( $this->admin );
		$role_to_test = 'norole';
		$global_id = \GraphQLRelay\Relay::toGlobalId( 'user_role', $role_to_test );

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

		$actual = graphql(['query' => $query]);
		codecept_debug( $actual );

		$this->assertEquals( $expected, $actual['errors'][0]['message'] );
		$this->assertNull( $actual['data']['userRole'] );

	}

	/**
	 * Test that a user is properly returned
	 *
	 * @throws Exception
	 */
	public function testUserRoleQuery() {

		wp_set_current_user( $this->admin );
		$role_to_test = 'administrator';

		$global_id = \GraphQLRelay\Relay::toGlobalId( 'user_role', $role_to_test );
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
			'userRole' => [
				'id' => $global_id,
				'name' => $role_to_test,
				'capabilities' => array_keys( $role_obj->capabilities, true, true ),
			],
		];

		$actual = graphql(['query' => $query]);
		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $expected, $actual['data'] );

	}



}
