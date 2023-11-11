<?php

class ShouldShowAdminToolbarQueryTest extends \Codeception\TestCase\WPTestCase {

	public function setUp(): void {
		parent::setUp();
		WPGraphQL::clear_schema();
	}

	public function tearDown(): void {
		WPGraphQL::clear_schema();
		parent::tearDown();
	}

	public function testViewerQuery() {

		$user_id = $this->factory->user->create(
			[
				'role' => 'administrator',
			]
		);

		$query = '
		{
			viewer{
				userId
				roles {
						nodes {
							name
						}
				}
        shouldShowAdminToolbar
			}
		}
		';

		/**
		 * Set the current user so we can properly test the viewer query
		 */
		wp_set_current_user( $user_id );

		$actual = graphql( [ 'query' => $query ] );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $user_id, $actual['data']['viewer']['userId'] );
		$this->assertSame( true, $actual['data']['viewer']['shouldShowAdminToolbar'] );

		// Update the user's preference to not show admin bar.
		update_user_meta( $user_id, 'show_admin_bar_front', 'false' );

		$actual = graphql( [ 'query' => $query ] );

		codecept_debug( $actual );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $user_id, $actual['data']['viewer']['userId'] );
		$this->assertSame( false, $actual['data']['viewer']['shouldShowAdminToolbar'] );
	}
}
