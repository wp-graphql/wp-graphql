<?php

use GraphQLRelay\Relay;

class MenuItemQueriesTest extends \Codeception\TestCase\WPTestCase {

	public $admin;

	public function setUp(): void {
		parent::setUp();
		$this->admin = $this->factory()->user->create([
			'role' => 'administrator'
		]);
		WPGraphQL::clear_schema();}

	public function tearDown(): void {
		WPGraphQL::clear_schema();
		parent::tearDown();
	}

	public function testMenuItemQuery() {

		add_theme_support( 'nav_menus' );
		$location_name = 'test-location';
		register_nav_menu( $location_name, 'test menu...' );

		$menu_slug = 'my-test-menu';
		$menu_id = wp_create_nav_menu( $menu_slug );
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

		set_theme_mod( 'nav_menu_locations', [ $location_name => $menu_id ] );

		codecept_debug( get_theme_mod( 'nav_menu_locations' ) );

		$menu_item_relay_id = Relay::toGlobalId( 'nav_menu_item', $menu_item_id );

		$query = '
		{
			menuItem( id: "' . $menu_item_relay_id . '" ) {
				id
				databaseId
				connectedObject {
					... on Post {
						id
						postId
					}
				}
				locations
				menu {
				  node {
				    slug
				    locations
				  }
				}
			}
		}
		';

		$actual = do_graphql_request( $query );


		codecept_debug( $actual );

		$this->assertEquals( $menu_item_id, $actual['data']['menuItem']['databaseId'] );
		$this->assertEquals( $menu_item_relay_id, $actual['data']['menuItem']['id'] );
		$this->assertEquals( $post_id, $actual['data']['menuItem']['connectedObject']['postId'] );
		$this->assertEquals( $menu_slug, $actual['data']['menuItem']['menu']['node']['slug'] );
		$this->assertEquals( [ \WPGraphQL\Type\WPEnumType::get_safe_name( $location_name ) ], $actual['data']['menuItem']['locations'] );
		$this->assertEquals( [ \WPGraphQL\Type\WPEnumType::get_safe_name( $location_name ) ], $actual['data']['menuItem']['menu']['node']['locations'] );
	}

}
