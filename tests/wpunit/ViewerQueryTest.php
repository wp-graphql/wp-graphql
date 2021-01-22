<?php

class ViewerQueryTest extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		parent::setUp();
	}

	public function tearDown(): void {
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
		    roles {
		        nodes {
		          name
		        }
		    }
		  }
		}
		';

		$actual = do_graphql_request( $query );

		/**
		 * We should get an error because no user is logged in right now
		 */
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertNull( $actual['data']['viewer'] );

		/**
		 * Set the current user so we can properly test the viewer query
		 */
		wp_set_current_user( $user_id );
		$actual = graphql([ 'query' => $query ]);

		codecept_debug( $actual );

		$this->assertNotEmpty( $actual );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $user_id, $actual['data']['viewer']['userId'] );
		$this->assertSame( 'administrator', $actual['data']['viewer']['roles']['nodes'][0]['name'] );

	}

}
