<?php

use GraphQLRelay\Relay;

class MenuItemQueriesTest extends  \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $admin;
	public $location_name;
	public $menu_id;
	public $menu_slug;


	public function setUp(): void {
		parent::setUp();
		$this->admin = $this->factory()->user->create([
			'role' => 'administrator',
		]);

		add_theme_support( 'nav_menus' );

		$this->location_name = 'test-location';
		register_nav_menu( $this->location_name, 'test menu...' );

		$this->menu_slug = 'my-test-menu';
		$this->menu_id   = wp_create_nav_menu( $this->menu_slug );

		set_theme_mod( 'nav_menu_locations', [ $this->location_name => $this->menu_id ] );

		WPGraphQL::clear_schema();
	}

	public function tearDown(): void {
		remove_theme_support( 'nav_menus' );
		wp_delete_nav_menu( $this->menu_id );
		unregister_nav_menu( $this->location_name );

		WPGraphQL::clear_schema();
		parent::tearDown();
	}

	public function testMenuItemQuery() {
		$post_id = $this->factory()->post->create();

		$menu_item_id = wp_update_nav_menu_item(
			$this->menu_id,
			0,
			[
				'menu-item-title'     => 'Menu item',
				'menu-item-object'    => 'post',
				'menu-item-object-id' => $post_id,
				'menu-item-status'    => 'publish',
				'menu-item-type'      => 'post_type',
			]
		);

		codecept_debug( get_theme_mod( 'nav_menu_locations' ) );

		$menu_item_relay_id = Relay::toGlobalId( 'post', $menu_item_id );

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

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertEquals( $menu_item_id, $actual['data']['menuItem']['databaseId'] );
		$this->assertEquals( $menu_item_relay_id, $actual['data']['menuItem']['id'] );
		$this->assertEquals( $post_id, $actual['data']['menuItem']['connectedObject']['postId'] );
		$this->assertEquals( $this->menu_slug, $actual['data']['menuItem']['menu']['node']['slug'] );
		$this->assertEquals( [ \WPGraphQL\Type\WPEnumType::get_safe_name( $this->location_name ) ], $actual['data']['menuItem']['locations'] );
		$this->assertEquals( [ \WPGraphQL\Type\WPEnumType::get_safe_name( $this->location_name ) ], $actual['data']['menuItem']['menu']['node']['locations'] );

		$old_id = Relay::toGlobalId( 'nav_menu_itemci', $menu_item_id );

		$query = '
		{
			menuItem( id: "' . $old_id . '" ) {
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

		$actual = $this->graphql( compact( 'query' ) );

		$this->assertEquals( $menu_item_id, $actual['data']['menuItem']['databaseId'] );
		$this->assertEquals( $menu_item_relay_id, $actual['data']['menuItem']['id'] );
		$this->assertEquals( $post_id, $actual['data']['menuItem']['connectedObject']['postId'] );
		$this->assertEquals( $this->menu_slug, $actual['data']['menuItem']['menu']['node']['slug'] );
		$this->assertEquals( [ \WPGraphQL\Type\WPEnumType::get_safe_name( $this->location_name ) ], $actual['data']['menuItem']['locations'] );
		$this->assertEquals( [ \WPGraphQL\Type\WPEnumType::get_safe_name( $this->location_name ) ], $actual['data']['menuItem']['menu']['node']['locations'] );

	}

}
