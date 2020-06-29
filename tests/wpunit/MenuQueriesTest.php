<?php

use GraphQLRelay\Relay;

class MenuQueriesTest extends \Codeception\TestCase\WPTestCase {

	public $admin;

	public function setUp(): void {
		parent::setUp();
		$this->admin = $this->factory()->user->create([
			'role' => 'administrator'
		]);
	}

	public function testMenuQuery() {
		$menu_slug = 'my-test-menu';
		$menu_id = wp_create_nav_menu( $menu_slug );
		$menu_relay_id = Relay::toGlobalId( 'term', $menu_id );

		$query = '
		{
			menu( id: "' . $menu_relay_id . '" ) {
				id
				menuId
				name
			}
		}
		';

		$actual = do_graphql_request( $query );

		// A menu not associated with a location is private for a public request
		$this->assertNull( $actual['data']['menu'] );

		wp_set_current_user( $this->admin );

		$actual = do_graphql_request( $query );

		$this->assertEquals( $menu_id, $actual['data']['menu']['menuId'] );
		$this->assertEquals( $menu_relay_id, $actual['data']['menu']['id'] );
		$this->assertEquals( $menu_slug, $actual['data']['menu']['name'] );
	}

}
