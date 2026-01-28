<?php
namespace WPGraphQL\SmartCache;

use TestCase\WPGraphQLSmartCache\TestCase\WPGraphQLSmartCacheTestCaseWithSeedDataAndPopulatedCaches;

class MenuItemCacheInvalidationTest extends WPGraphQLSmartCacheTestCaseWithSeedDataAndPopulatedCaches {

	public function setUp(): void {
		parent::setUp();
		$this->_populateCaches();
		$this->assertEmpty( $this->getEvictedCaches() );

	}

	public function testItWorks(): void {
		$this->assertTrue( true );
	}

	// adding a new menu item to a menu that's assigned to a location should purge list:menuItem
	public function testAddMenuItemToPublicMenuPurgesListMenuItem(): void {

		// Create a new menu item
		$new_nav_menu_item = self::factory()->post->create([
			'post_type' => 'nav_menu_item',
			'post_status' => 'publish',
		]);

		// Update nav menu item. This is ran when menu items are created and this generates
		// the relationship between the menu item and menu
		wp_update_nav_menu_item( $this->public_menu->term_id, $new_nav_menu_item, [
			'menu-item-title' => 'New Public Menu Item',
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $this->published_page->ID,
			'menu-item-status'    => 'publish',
			'menu-item-type'      => 'post_type',
		]);

		$evicted_caches = $this->getEvictedCaches();

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets( $evicted_caches, [
			'listMenuItem',
		]);

	}
	// editing a menu item on a menu that's assigned to a location should purge the ID of said menu item
	public function testUpdateMenuItemOnPublicMenuPurgesListMenuItem(): void {

		$this->assertEmpty( $this->getEvictedCaches() );

		wp_update_nav_menu_item( $this->public_menu->term_id, $this->menu_item_1->ID, [
			'menu-item-title' => 'Updated Menu Item Title',
			'menu-item-description' => 'Updated Menu Item Description'
		]);

		$evicted_caches = $this->getEvictedCaches();

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets( $evicted_caches, [
			'listMenuItem',
			'singleMenuItem'
		]);

	}
	// removing a menu item on a menu that's assigned to a location should purge the ID of said menu item
	public function testDeleteMenuItemOnPublicMenuPurgesListMenuItem(): void {

		$this->assertEmpty( $this->getEvictedCaches() );

		wp_delete_nav_menu( $this->public_menu->term_id );

		$evicted_caches = $this->getEvictedCaches();

		$this->assertNotEmpty( $evicted_caches );

		$this->assertEqualSets( $evicted_caches, [
			'listMenuItem',
			'singleMenuItem',
			'listMenu',
			'singleMenu',
			'singleChildMenuItem'
		]);

	}

	// adding a new menu item to a menu that's NOT assigned to a location should not emit a purge event
	public function testAddMenuItemToPrivateMenuDoesNotPurgeCache(): void {

		// Create a new menu item
		$new_nav_menu_item = self::factory()->post->create([
			'post_type' => 'nav_menu_item',
			'post_status' => 'publish',
		]);

		wp_update_nav_menu_item( $this->private_menu->term_id, $new_nav_menu_item, [
			'menu-item-title' => 'New Private Menu Item',
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $this->published_page->ID,
			'menu-item-status'    => 'publish',
			'menu-item-type'      => 'post_type',
		]);

		$evicted_caches = $this->getEvictedCaches();

		$this->assertEmpty( $evicted_caches );

	}

	// editing a menu item on a menu that's NOT assigned to a location should not emit a purge event
	public function testUpdateMenuItemOnPrivateMenuDoesNotPurgeCaches(): void {

		$this->assertEmpty( $this->getEvictedCaches() );

		wp_update_nav_menu_item( $this->private_menu->term_id, $this->private_menu_item->ID, [
			'menu-item-title' => 'Updated Menu Item Title',
			'menu-item-description' => 'Updated Menu Item Description'
		]);

		$evicted_caches = $this->getEvictedCaches();

		$this->assertEmpty( $evicted_caches );

	}

	// removing a menu item on a menu that's NOT assigned to a location should not emit a purge event
	public function testDeleteMenuItemOnPrivateMenuDoesNotPurgeCaches(): void {

		$this->assertEmpty( $this->getEvictedCaches() );

		wp_delete_nav_menu( $this->private_menu->term_id );

		$evicted_caches = $this->getEvictedCaches();

		$this->assertEmpty( $evicted_caches );

	}

}
