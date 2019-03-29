<?php

use GraphQLRelay\Relay;

class MenuItemQueriesTest extends \Codeception\TestCase\WPTestCase {

	public function testMenuItemQuery() {
		$menu_id = wp_create_nav_menu( 'my-test-menu' );
		$post_id = $this->factory()->post->create();

		$menu_item_id = wp_update_nav_menu_item(
			$menu_id,
			0,
			[
				'menu-item-title'     => 'Menu item',
				'menu-item-object'    => 'post',
				'menu-item-object-id' => $post_id,
				'menu-item-status'    => 'publish',
				'menu-item-type'      => 'post_type',
			]
		);

		$menu_item_relay_id = Relay::toGlobalId( 'nav_menu_item', $menu_item_id );

		$query = '
		{
			menuItem( id: "' . $menu_item_relay_id . '" ) {
				id
				menuItemId
				connectedObject {
					... on Post {
						id
						postId
					}
				}
			}
		}
		';

		$actual = do_graphql_request( $query );

		$this->assertEquals( $menu_item_id, $actual['data']['menuItem']['menuItemId'] );
		$this->assertEquals( $menu_item_relay_id, $actual['data']['menuItem']['id'] );
		$this->assertEquals( $post_id, $actual['data']['menuItem']['connectedObject']['postId'] );
	}

}
