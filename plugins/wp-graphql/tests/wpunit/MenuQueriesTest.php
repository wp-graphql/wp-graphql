<?php

use GraphQLRelay\Relay;
use WPGraphQL\Type\WPEnumType;

class MenuQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $admin;
	public $location_name;
	public $menu_id;
	public $menu_slug;
	public $menu_item_id;

	public function setUp(): void {
		parent::setUp();
		$this->admin         = $this->factory()->user->create(
			[
				'role' => 'administrator',
			]
		);
		$this->location_name = 'test-location';
		register_nav_menu( $this->location_name, 'test menu...' );

		$this->menu_slug = 'my-test-menu';
		$this->menu_id   = wp_create_nav_menu( $this->menu_slug );

		$menu_item_args = [
			'menu-item-title'     => 'Parent Item',
			'menu-item-parent-id' => 0,
			'menu-item-url'       => 'http://example.com/',
			'menu-item-status'    => 'publish',
			'menu-item-type'      => 'custom',
		];

		$this->menu_item_id = wp_update_nav_menu_item( $this->menu_id, 0, $menu_item_args );
	}

	public function tearDown(): void {
		remove_theme_support( 'nav_menus' );
		wp_delete_nav_menu( $this->menu_id );
		unregister_nav_menu( $this->location_name );

		WPGraphQL::clear_schema();
		parent::tearDown();
	}

	public function get_query() {
		return '
			query menu( $id: ID!, $idType: MenuNodeIdTypeEnum ) {
				menu( id: $id, idType: $idType ) {
					count
					databaseId
					id
					locations
					name
					slug
					menuItems {
						nodes {
							databaseId
						}
					}
				}
			}
		';
	}

	public function testMenuQuery() {
		$query = $this->get_query();

		$menu_relay_id = Relay::toGlobalId( 'term', $this->menu_id );

		// Test by DatabaseId
		$variables = [
			'id'     => $this->menu_id,
			'idType' => 'DATABASE_ID',
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		// A menu not associated with a location is private for a public request
		$this->assertNull( $actual['data']['menu'] );

		wp_set_current_user( $this->admin );

		$actual   = $this->graphql( compact( 'query', 'variables' ) );
		$expected = wp_get_nav_menu_object( $this->menu_id );

		$this->assertArrayNotHasKey( 'errors', $actual );

		$this->assertEquals( $expected->count, $actual['data']['menu']['count'] );
		$this->assertEquals( $this->menu_id, $actual['data']['menu']['databaseId'] );
		$this->assertNull( $actual['data']['menu']['locations'] );
		$this->assertEquals( $menu_relay_id, $actual['data']['menu']['id'] );
		$this->assertEquals( $expected->name, $actual['data']['menu']['name'] );
		$this->assertEquals( $expected->slug, $actual['data']['menu']['slug'] );
		$this->assertEquals( $this->menu_item_id, $actual['data']['menu']['menuItems']['nodes'][0]['databaseId'] );

		// Test with global ID
		$variables = [
			'id'     => $menu_relay_id,
			'idType' => 'ID',
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $this->menu_id, $actual['data']['menu']['databaseId'] );
		$this->assertEquals( $menu_relay_id, $actual['data']['menu']['id'] );

		// Test with name.
		$variables = [
			'id'     => $expected->name,
			'idType' => 'NAME',
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $this->menu_id, $actual['data']['menu']['databaseId'] );
		$this->assertEquals( $menu_relay_id, $actual['data']['menu']['id'] );

		// Test with slug.
		$variables = [
			'id'     => $this->menu_slug,
			'idType' => 'SLUG',
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $this->menu_id, $actual['data']['menu']['databaseId'] );
		$this->assertEquals( $menu_relay_id, $actual['data']['menu']['id'] );
	}

	public function testMenuQueryByLocation() {
		set_theme_mod( 'nav_menu_locations', [ $this->location_name => $this->menu_id ] );

		$query = $this->get_query();

		// Test by DatabaseId
		$variables = [
			'id'     => $this->location_name,
			'idType' => 'LOCATION',
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $this->menu_id, $actual['data']['menu']['databaseId'] );

		$locations = get_nav_menu_locations();
		$this->assertEquals( WPEnumType::get_safe_name( array_search( $this->menu_id, $locations, true ) ), $actual['data']['menu']['locations'][0] );
	}

	public function testUnicodeSlugsAreDecoded() {
		$unicode_slug = 'חדשות';
		$menu_id      = wp_create_nav_menu( $unicode_slug );

		$menu_item_args = [
			'menu-item-title'     => 'Parent Item',
			'menu-item-parent-id' => 0,
			'menu-item-url'       => 'http://example.com/',
			'menu-item-status'    => 'publish',
			'menu-item-type'      => 'custom',
		];

		$menu_item_id = wp_update_nav_menu_item( $menu_id, 0, $menu_item_args );

		set_theme_mod( 'nav_menu_locations', [ $this->location_name => $menu_id ] );

		$query = $this->get_query();

		$variables = [
			'id'     => $unicode_slug,
			'idType' => 'SLUG',
		];

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEquals( $menu_id, $actual['data']['menu']['databaseId'] );
		$this->assertEquals( $unicode_slug, $actual['data']['menu']['slug'] );
	}
}
