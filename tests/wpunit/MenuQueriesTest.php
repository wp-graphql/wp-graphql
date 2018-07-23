<?php

use GraphQLRelay\Relay;

class MenuQueriesTest extends \Codeception\TestCase\WPTestCase {

	public function testMenuQuery() {
		$menu_slug = 'my-test-menu';
		$menu_id = wp_create_nav_menu( $menu_slug );
		$menu_relay_id = Relay::toGlobalId( 'Menu', $menu_id );

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

		$this->assertEquals( $menu_id, $actual['data']['menu']['menuId'] );
		$this->assertEquals( $menu_relay_id, $actual['data']['menu']['id'] );
		$this->assertEquals( $menu_slug, $actual['data']['menu']['name'] );
	}

}
