<?php

use WPGraphQL\Type\Enum\MenuLocationEnumType;

class MenuItemConnectionQueriesTest extends \Codeception\TestCase\WPTestCase {

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		add_theme_support( 'nav_menu_locations' );
		register_nav_menu( 'my-menu-location', 'My Menu' );
		set_theme_mod( 'nav_menu_locations', [ 'my-menu-location' => 0 ] );
	}

	private function createMenuItem( $menu_id, $options ) {
		return wp_update_nav_menu_item( $menu_id, 0, $options );
	}

	private function createMenuItems( $slug, $count ) {
		$menu_id = wp_create_nav_menu( $slug );
		$menu_item_ids = [];
		$post_ids = [];

		// Create some Post menu items.
		for ( $x = 1; $x <= $count; $x++ ) {
			$post_id = $this->factory()->post->create();
			$post_ids[] = $post_id;

			$menu_item_ids[] = $this->createMenuItem(
				$menu_id,
				[
					'menu-item-title'     => "Menu item {$x}",
					'menu-item-object'    => 'post',
					'menu-item-object-id' => $post_id,
					'menu-item-status'    => 'publish',
					'menu-item-type'      => 'post_type',
				]
			);
		}

		// Assign menu to location.
		set_theme_mod( 'nav_menu_locations', [ 'my-menu-location' => $menu_id ] );

		return [
			'menu_id'       => $menu_id,
			'menu_item_ids' => $menu_item_ids,
			'post_ids'      => $post_ids,
		];
	}

	public function testMenuItemsQueryWithNoArgs() {
		$count = 10;
		$created = $this->createMenuItems( 'my-test-menu-id', $count );

		$query = '
		{
			menuItems {
				edges {
					node {
						menuItemId
						connectedObject {
							... on Post {
								postId
							}
						}
					}
				}
			}
		}
		';

		$actual = do_graphql_request( $query );

		// Make sure menu items were created.
		$this->assertEquals( $count, count( $created['menu_item_ids'] ) );
		$this->assertEquals( $count, count( $created['post_ids'] ) );

		// The query should return no menu items since no where args were specified.
		$this->assertEquals( 0, count( $actual['data']['menuItems']['edges'] ) );
	}

	public function testMenuItemsQueryById() {
		$count = 10;
		$created = $this->createMenuItems( 'my-test-menu-id', $count );

		$menu_item_id = intval( $created['menu_item_ids'][2] );
		$post_id = intval( $created['post_ids'][2] );

		$query = '
		{
			menuItems( where: { id: ' . $menu_item_id . ' } ) {
				edges {
					node {
						menuItemId
						connectedObject {
							... on Post {
								postId
							}
						}
					}
				}
			}
		}
		';

		$actual = do_graphql_request( $query );

		// The returned menu items have the expected number.
		$this->assertEquals( 1, count( $actual['data']['menuItems']['edges'] ) );

		// The returned menu items and connected posts have the expected IDs.
		$this->assertEquals( $menu_item_id, $actual['data']['menuItems']['edges'][0]['node']['menuItemId'] );
		$this->assertEquals( $post_id, $actual['data']['menuItems']['edges'][0]['node']['connectedObject']['postId'] );
	}

	public function testMenuItemsQueryByLocation() {
		$count = 10;
		$created = $this->createMenuItems( 'my-test-menu-location', $count );

		$query = '
		{
			menuItems( where: { location: MY_MENU_LOCATION } ) {
				edges {
					node {
						menuItemId
						connectedObject {
							... on Post {
								postId
							}
						}
					}
				}
			}
		}
		';

		$actual = do_graphql_request( $query );

		// The returned menu items have the expected number.
		$this->assertEquals( $count, count( $actual['data']['menuItems']['edges'] ) );

		// The returned menu items and connected posts have the expected IDs.
		foreach( $actual['data']['menuItems']['edges'] as $menu_item ) {
			$this->assertTrue( in_array( $menu_item['node']['menuItemId'], $created['menu_item_ids'], true ) );
			$this->assertTrue( in_array( $menu_item['node']['connectedObject']['postId'], $created['post_ids'], true ) );
		}
	}

	public function testMenuItemsQueryWithChildItems() {
		$count = 10;
		$created = $this->createMenuItems( 'my-test-menu-with-child-items', $count );

		// Add some child items to the fourth menu item.
		$child_count = 3;
		for ( $x = 1; $x <= $child_count; $x++ ) {
			$options = [
				'menu-item-title'     => "Child menu item {$x}",
				'menu-item-object'    => 'post',
				'menu-item-object-id' => $this->factory()->post->create(),
				'menu-item-parent-id' => $created['menu_item_ids'][3],
				'menu-item-status'    => 'publish',
				'menu-item-type'      => 'post_type',
			];

			$this->createMenuItem( $created['menu_id'], $options );
		}

		$query = '
		{
			menuItems( where: { location: MY_MENU_LOCATION } ) {
				edges {
					node {
						menuItemId
						connectedObject {
							... on Post {
								postId
							}
						}
						childItems {
							edges {
								node {
									menuItemId
									connectedObject {
										... on Post {
											postId
										}
									}
								}
							}
						}
					}
				}
			}
		}
		';

		$actual = do_graphql_request( $query );

		// The returned menu items have the expected number.
		$this->assertEquals( $count, count( $actual['data']['menuItems']['edges'] ) );

		// The returned menu items and connected posts have the expected IDs.
		foreach( $actual['data']['menuItems']['edges'] as $menu_item ) {
			$this->assertTrue( in_array( $menu_item['node']['menuItemId'], $created['menu_item_ids'], true ) );
			$this->assertTrue( in_array( $menu_item['node']['connectedObject']['postId'], $created['post_ids'], true ) );
		}

		// The fourth menu item has the expected number of child items.
		$this->assertEquals( $child_count, count( $actual['data']['menuItems']['edges'][3]['node']['childItems']['edges'] ) );
	}

}
