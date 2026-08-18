<?php

class MenuItemConnectionResolverTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	public function setUp(): void {
		// before
		parent::setUp();

		// your set up methods here
		WPGraphQL::clear_schema();
	}

	public function tearDown(): void {
		// your tear down methods here
		WPGraphQL::clear_schema();
		// then
		parent::tearDown();
	}

	public function testMenuItemConnectionResolverWithNoMenuItem() {

		$query = '
		{
			menuItems {
				nodes {
				id
				}
			}
			}
		';

		$actual = do_graphql_request( $query );
		$this->assertEmpty( $actual['data']['menuItems']['nodes'] );
		$this->assertArrayNotHasKey( 'errors', $actual );
	}

	/**
	 * Create a nav menu that is NOT assigned to any menu location, with a single
	 * published menu item, and return the menu item database ID.
	 *
	 * @return int
	 */
	private function create_unassigned_menu_item() {
		$menu_id = wp_create_nav_menu( 'unassigned-menu-' . uniqid() );
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		return wp_update_nav_menu_item(
			$menu_id,
			0,
			[
				'menu-item-title'     => 'Unassigned item',
				'menu-item-object'    => 'post',
				'menu-item-object-id' => $post_id,
				'menu-item-status'    => 'publish',
				'menu-item-type'      => 'post_type',
			]
		);
	}

	/**
	 * By default, public requests should only see menu items assigned to a
	 * registered menu location. Items belonging to a menu with no location
	 * stay hidden. This locks the privacy default (see #3043).
	 */
	public function testUnassignedMenuItemsHiddenFromPublicByDefault() {
		$menu_item_id = $this->create_unassigned_menu_item();

		wp_set_current_user( 0 );

		$query  = '{ menuItems { nodes { databaseId } } }';
		$actual = do_graphql_request( $query );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertEmpty( $actual['data']['menuItems']['nodes'] );
	}

	/**
	 * The "Make Menus and Menu Items public" recipe flips the MenuItem Model
	 * privacy gate via `graphql_data_is_private`, but prior to this fix the
	 * connection's location `tax_query` still stripped items belonging to a
	 * menu with no assigned location, so the recipe had no effect (#3080).
	 *
	 * With the Model gate flipped AND the connection location restriction
	 * disabled via `graphql_menu_item_connection_restrict_to_locations`, an
	 * anonymous request should see the unassigned menu's items.
	 */
	public function testUnassignedMenuItemsVisibleToPublicWhenLocationRestrictionDisabled() {
		$menu_item_id = $this->create_unassigned_menu_item();

		wp_set_current_user( 0 );

		// Mirror the recipe: make MenuItem models public.
		$model_filter = static function ( $is_private, $model_name ) {
			return 'MenuItemObject' === $model_name ? false : $is_private;
		};
		add_filter( 'graphql_data_is_private', $model_filter, 10, 2 );

		// Lift the connection's implicit location restriction for public requests.
		$connection_filter = static function () {
			return false;
		};
		add_filter( 'graphql_menu_item_connection_restrict_to_locations', $connection_filter );

		$query  = '{ menuItems { nodes { databaseId } } }';
		$actual = do_graphql_request( $query );

		remove_filter( 'graphql_data_is_private', $model_filter, 10 );
		remove_filter( 'graphql_menu_item_connection_restrict_to_locations', $connection_filter );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$database_ids = wp_list_pluck( $actual['data']['menuItems']['nodes'], 'databaseId' );
		$this->assertContains( $menu_item_id, $database_ids );
	}
}
