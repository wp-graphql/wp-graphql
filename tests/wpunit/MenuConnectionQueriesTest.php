<?php

use WPGraphQL\Type\WPEnumType;

class MenuConnectionQueriesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public $admin;

	public function setUp():void {
		// before

		$this->clearSchema();
		parent::setUp();

		$this->admin = $this->factory()->user->create([
			'role' => 'administrator',
		]);

		add_theme_support( 'nav_menu_locations' );
		register_nav_menu( 'my-menu-location', 'My Menu' );
		set_theme_mod( 'nav_menu_locations', [ 'my-menu-location' => 0 ] );
	}

	public function tearDown(): void {
		// your tear down methods here
		$this->clearSchema();
		parent::tearDown();
	}

	public function testMenusQueryById() {
		$menu_slug = 'my-test-menu-by-id';
		$menu_id   = wp_create_nav_menu( $menu_slug );

		codecept_debug( $menu_id );

		$query = '
		{
			menus( where: { id: ' . intval( $menu_id ) . ' } ) {
				edges {
					node {
						menuId
						id
						name
					}
				}
			}
		}
		';

		$actual = $this->graphql( [ 'query' => $query ] );

		// a public request should get no menus if there are none associated with a location
		$this->assertSame( [], $actual['data']['menus']['edges'] );

		wp_set_current_user( $this->admin );
		$actual = $this->graphql( [ 'query' => $query ] );

		$this->assertEquals( 1, count( $actual['data']['menus']['edges'] ) );
		$this->assertEquals( $menu_id, $actual['data']['menus']['edges'][0]['node']['menuId'] );
		$this->assertEquals( $menu_slug, $actual['data']['menus']['edges'][0]['node']['name'] );
	}

	public function testMenusQueryByLocation() {

		/**
		 * Create multiple menus so that we can test querying for 1 and ensure
		 * we get it back properly.
		 */
		$menu_slug = 'my-test-menu-by-location';
		wp_create_nav_menu( $menu_slug );
		wp_create_nav_menu( $menu_slug . '-2' );
		$id_3 = wp_create_nav_menu( $menu_slug . '-3' );
		wp_create_nav_menu( $menu_slug . '-4' );

		$menu_location = 'my-menu-location';

		$query = '
			query queryMenus( $location: MenuLocationEnum ) {
				menus( where: { location: $location } ) {
					edges {
						node {
							menuId
							name
						}
					}
				}
			}
		';

		$variables = [
			'location' => WPEnumType::get_safe_name( $menu_location ),
		];

		// Test when no menu is assigned to location.
		$actual = $this->graphql( compact( 'query', 'variables' ) );
		$this->assertSame( [], $actual['data']['menus']['edges'] );

		// Test with menu is assigned to location.
		set_theme_mod( 'nav_menu_locations', [ $menu_location => $id_3 ] );

		$actual = $this->graphql( compact( 'query', 'variables' ) );

		$this->assertEquals( 1, count( $actual['data']['menus']['edges'] ) );
		$this->assertEquals( $id_3, $actual['data']['menus']['edges'][0]['node']['menuId'] );
		$this->assertEquals( $menu_slug . '-3', $actual['data']['menus']['edges'][0]['node']['name'] );
	}

	public function testMenusQueryBySlug() {
		$menu_slug = 'my-test-menu-by-slug';
		$menu_id   = wp_create_nav_menu( $menu_slug );

		$query = '
		{
			menus( where: { slug: "' . $menu_slug . '" } ) {
				edges {
					node {
						menuId
						name
					}
				}
			}
		}
		';

		$actual = $this->graphql( [ 'query' => $query ] );

		// a public request should get no menus if there are none associated with a location
		$this->assertSame( [], $actual['data']['menus']['edges'] );

		wp_set_current_user( $this->admin );
		$actual = $this->graphql( [ 'query' => $query ] );

		$this->assertEquals( 1, count( $actual['data']['menus']['edges'] ) );
		$this->assertEquals( $menu_id, $actual['data']['menus']['edges'][0]['node']['menuId'] );
		$this->assertEquals( $menu_slug, $actual['data']['menus']['edges'][0]['node']['name'] );
	}

	public function testMenusQueryMultiple() {
		$menu_ids = [
			wp_create_nav_menu( 'my-test-menu-1' ),
			wp_create_nav_menu( 'my-test-menu-2' ),
			wp_create_nav_menu( 'my-test-menu-3' ),
		];

		$query = '
		{
			menus {
				edges {
					node {
						menuId
						name
					}
				}
			}
		}
		';

		$actual = $this->graphql( [ 'query' => $query ] );

		// a public request should get no menus if there are none associated with a location
		$this->assertSame( [], $actual['data']['menus']['edges'] );

		wp_set_current_user( $this->admin );
		$actual = $this->graphql( [ 'query' => $query ] );

		$this->assertEquals( count( $menu_ids ), count( $actual['data']['menus']['edges'] ) );
		foreach ( $menu_ids as $index => $menu_id ) {
			$this->assertEquals( $menu_id, $actual['data']['menus']['edges'][ $index ]['node']['menuId'] );
		}
	}

}
