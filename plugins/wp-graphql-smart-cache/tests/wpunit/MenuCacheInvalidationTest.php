<?php
namespace WPGraphQL\SmartCache;

use GraphQLRelay\Relay;
use TestCase\WPGraphQLSmartCache\TestCase\WPGraphQLSmartCacheTestCaseWithSeedDataAndPopulatedCaches;

class MenuCacheInvalidationTest extends WPGraphQLSmartCacheTestCaseWithSeedDataAndPopulatedCaches {


	public function setUp(): void {
		parent::setUp();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	public function testItWorks() {
		$this->assertTrue( true );
	}

	// create nav menu (doesn't purge)
	public function testCreateNavMenuDoesntEvictCaches() {

		$this->assertEmpty( $this->getEvictedCaches() );

		wp_create_nav_menu( 'Test Menu' );

		$this->assertEmpty( $this->getEvictedCaches() );

	}

	// assign nav menu to location (purge)
	public function testAssignNavMenuToLocationEvictsQueriesForMenus() {

		$location_name = 'test-location';

		// register a menu location
		register_nav_menu( $location_name, 'Test Menu Location' );

		// clear the nav_menu_locations so we can start with no
		// menus assigned to any locations
		set_theme_mod( 'nav_menu_locations', null );

		// Reset the caches / evictions
		$this->_populateCaches();

		// creating a menu shouldn't have evicted any caches
		$this->assertEmpty( $this->getEvictedCaches() );

		// assign the menu to a location. This should evict caches for queries for menus
		set_theme_mod( 'nav_menu_locations', [ $location_name => (int) $this->public_menu->term_id ] );

		$evicted_caches = $this->getEvictedCaches();

		$this->assertNotEmpty( $this->getEvictedCaches() );

		$this->assertEqualSets([

			// assigning a menu to a location should purge the
			// cache of the listMenu query as this would be
			// similar to creating a new menu (making a menu public)
			'listMenu'
		], $evicted_caches );

	}

	// update nav menu (which is assigned to location, should purge)
	public function testUpdateMenuThatIsAssignedToALocationShouldEvictCaches() {

		$this->assertEmpty( $this->getEvictedCaches() );

		wp_update_nav_menu_object( $this->public_menu->term_id, [
			'menu-name' => $this->public_menu->name,
			'description' => 'updated description...',
		] );

		$evicted = $this->getEvictedCaches();

		// Adding a new menu item should evict caches
		$this->assertNotEmpty( $evicted );


		$this->assertEqualSets([

			// the menu queried by singleMenu query was updated and should be evicted
			'singleMenu',

			// the list menu should be evicted because the menu was in its results
			'listMenu'

		], $evicted );


	}

	// update nav menu (not assigned to a location, no evictions)
	public function testUpdateNavMenuNotAssignedToALocationDoesNotEvictCache() {

		$unassigned_menu_id = wp_create_nav_menu( 'Unassigned Menu' );

		// uncomment below 3 lines to test the behavior when the menu is assigned to a location
        // (should break the test as it should evict caches)
//		register_nav_menu( 'test', 'test' );
//		set_theme_mod( 'nav_menu_locations', [ 'test' => $unassigned_menu_id ] );
//		$this->_populateCaches();

		$this->assertEmpty( $this->getEvictedCaches() );

		wp_update_nav_menu_object( $unassigned_menu_id, [
			'menu-name' => 'Updated Menu Name',
			'description' => 'updated description...',
		] );


		$this->assertEmpty( $this->getEvictedCaches() );

	}

	// delete menu (assigned to location, evict)
	public function testDeleteMenuAssignedToALocationShouldEvictCache() {

		$this->assertEmpty( $this->getEvictedCaches() );

		wp_delete_nav_menu( $this->public_menu->term_id );

		$evicted = $this->getEvictedCaches();

		$this->assertNotEmpty( $evicted );

		$this->assertEqualSets([

			// deleting the menu that was in the singleMenu query should evict the query
			'singleMenu',

			// deleting a menu that was in the listMenu results should evict the query
			'listMenu',

			// deleting a menu that had this menu item in it should purge queries
			// for this menu item as it will also delete this menu item
			'singleChildMenuItem',

			// deleting a menu that had this menu item in it should purge queries
			// for this menu item as it will also delete this menu item
			'singleMenuItem',

			// Deleting a public menu should invalidate a query for a list of menuItems
			'listMenuItem'
		], $evicted );


	}

	// delete menu (not assigned to location, no evictions)
	public function testDeleteMenuNotAssignedToLocationDoesNotEvictCaches() {

		$unassigned_menu_id = wp_create_nav_menu( 'Unassigned Menu' );

		$this->assertEmpty( $this->getEvictedCaches() );

		wp_delete_nav_menu( $unassigned_menu_id );

		// there should be no evicted caches after deleting a menu that was
		$this->assertEmpty( $this->getEvictedCaches() );

	}

	public function testUpdateTermMetaOnMenuAssignedToALocationEvictsCache() {

		$this->assertEmpty( $this->getEvictedCaches() );

		// update term meta on a public menu _should_ evict cache
		update_term_meta( $this->public_menu->term_id, 'meta_key', uniqid( null, true ) );

		$evicted = $this->getEvictedCaches();

		$this->assertNotEmpty( $evicted );

		$this->assertEqualSets([

			// updating meta on the menu that was in the singleMenu query should evict the query
			'singleMenu',

			// updating menu on a menu that was in the listMenu results should evict the query
			'listMenu'
		], $evicted );

	}

	public function testDeleteTermMetaOnMenuAssignedToALocationEvictsCache() {

		// setup some term meta to start with
		update_term_meta( $this->public_menu->term_id, 'meta_key', uniqid( null, true ) );

		// reset caches as the update above would have evicted some
		$this->_populateCaches();

		// assert that there are no evicted caches at this point
		$this->assertEmpty( $this->getEvictedCaches() );

		// delete term meta on a public menu _should_ evict cache
		delete_term_meta( $this->public_menu->term_id, 'meta_key' );

		$evicted = $this->getEvictedCaches();

		$this->assertNotEmpty( $evicted );

		$this->assertEqualSets([

			// deleting meta on the menu that was in the singleMenu query should evict the query
			'singleMenu',

			// deleting meta on a menu that was in the listMenu results should evict the query
			'listMenu'
		], $evicted );

	}

	public function testUpdateMetaOnMenuItemOfMenuAssignedToALocationEvictsCache() {

		// set some initial meta
		update_post_meta( $this->menu_item_1->ID, 'some_key', uniqid( 'origin', true ) );

		// reset the caches
		$this->_populateCaches();

		$this->assertEmpty( $this->getEvictedCaches() );

		update_post_meta( $this->menu_item_1->ID, 'some_key', uniqid( 'update:', true ) );

		$evicted = $this->getEvictedCaches();

		// since we updated meta of a menu that's not public, we should have no evicted caches
		$this->assertNotEmpty( $evicted );

		$this->assertEqualSets([

			// updating meta on a menu item
			'singleMenuItem',

			// deleting meta on a menuItem that was in the listMenuItem results should evict the query
			'listMenuItem'
		], $evicted );


	}

	public function testDeleteMetaOnMenuItemOfMenuAssignedToALocationEvictsCache() {

		// set some initial meta
		update_post_meta( $this->menu_item_1->ID, 'meta_key', uniqid( null, true ) );

		// reset the caches
		$this->_populateCaches();

		// assert that there are no evicted caches at this point
		$this->assertEmpty( $this->getEvictedCaches() );

		// delete term meta on a public menu _should_ evict cache
		delete_post_meta( $this->menu_item_1->ID, 'meta_key' );

		$evicted = $this->getEvictedCaches();

		$this->assertNotEmpty( $evicted );

		$this->assertEqualSets([

			// deleting meta on the menu item that was in the singleMenuItem query should evict the query
			'singleMenuItem',

			// deleting meta on a menuItem that was in the listMenu results should evict the query
			'listMenuItem'

		], $evicted );

	}

	// create nav menu (doesn't purge by default, should purge if menu model is public)
	public function testCreateNavMenuEvictsCachesWhenMenuModelIsMadePublic() {

		// set the current user so they can update a resource
		wp_set_current_user( $this->admin->ID );

		// Filter Menu and MenuItem to be public models
		add_filter( 'graphql_data_is_private', function( $is_private, $model_name, $data, $visibility, $owner, $current_user ) {

			if ( 'MenuObject' === $model_name || 'MenuItemObject' === $model_name ) {
				return false;
			}

			return $is_private;

		}, 10, 6 );

		$this->assertEmpty( $this->getEvictedCaches() );

		// Create a nav menu
		wp_create_nav_menu( 'Test Menu With No Location' );

		$this->assertEqualSets([
			// Creating a menu should purge list of menus
			'listMenu'
		], $this->getEvictedCaches() );

	}

	// create nav menu (doesn't purge by default, should purge if menu model is public)
	public function testUpdateNavMenuEvictsCachesWhenMenuModelIsMadePublic() {

		// set the current user so they can update a resource
		wp_set_current_user( $this->admin->ID );

		// Filter Menu and MenuItem to be public models
		add_filter( 'graphql_data_is_private', function( $is_private, $model_name, $data, $visibility, $owner, $current_user ) {

			if ( 'MenuObject' === $model_name || 'MenuItemObject' === $model_name ) {
				return false;
			}

			return $is_private;

		}, 10, 6 );

		// Create a nav menu
		$menu_id = wp_create_nav_menu( 'Test Menu With No Location' );

		$purges = [];

		add_action( 'graphql_purge', function( $key, $event ) use ( &$purges ) {
			$purges[$key][] = $event;
		}, 10, 2 );

		// Update nav menu object
		wp_update_nav_menu_object( $menu_id, [
			'menu-name' => 'Update: Test Menu With No Location'
		] );

		// Assert that the menuId was purged, and skipped:term was purged
		$this->assertEqualSets([
			// Creating a menu should purge single menu
			Relay::toGlobalId( 'term', $menu_id ),
			'skipped:term',
		], array_keys( $purges ) );

	}

}
