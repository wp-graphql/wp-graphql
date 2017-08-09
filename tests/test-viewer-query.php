<?php
/**
 * WPGraphQL Test Viewer Queries
 * This tests the Viewer Query (current user)
 *
 * @package WPGraphQL
 * @since   0.0.5
 */

/**
 * Tests user object queries.
 */
class WP_GraphQL_Test_Viewer_Queries extends WP_UnitTestCase {
	/**
	 * This function is run before each method
	 *
	 * @since 0.0.5
	 */
	public function setUp() {
		parent::setUp();
	}

	/**
	 * Runs after each method.
	 *
	 * @since 0.0.5
	 */
	public function tearDown() {
		parent::tearDown();
	}
	public function testViewerQuery() {

		$user_id = $this->factory->user->create( [
			'role' => 'administrator',
		] );

		$query = '
		{
		  viewer{
		    userId
		    roles
		  }
		}
		';

		$actual = do_graphql_request( $query );

		/**
		 * We should get an error because no user is logged in right now
		 */
		$this->assertArrayHasKey( 'errors', $actual );

		/**
		 * Set the current user so we can properly test the viewer query
		 */
		wp_set_current_user( $user_id );
		$actual = do_graphql_request( $query );

		$this->assertNotEmpty( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $user_id, $actual['data']['viewer']['userId'] );
		$this->assertContains( 'administrator', $actual['data']['viewer']['roles'] );

	}

}
