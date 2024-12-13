<?php

use WPGraphQL\Admin\AdminNotices;

/**
 * Class AdminNoticesTest
 *
 * Tests the AdminNotices class functionality within the WPGraphQL plugin.
 */
class AdminNoticesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function setUp(): void {
		parent::setUp();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Test initialization of admin notices.
	 */
	public function testInit(): void {
		AdminNotices::get_instance();

		// Assertions to ensure actions are hooked correctly
		// This might require functional testing or integration testing setup
		$this->assertTrue( has_action( 'admin_notices' ) );
	}

	/**
	 * Test adding and retrieving admin notices.
	 */
	public function testAddAndGetAdminNotices(): void {
		$adminNotices = AdminNotices::get_instance();

		$slug   = 'test-notice';
		$config = [
			'message'        => 'Test Notice Message',
			'type'           => 'warning',
			'is_dismissable' => true,
		];

		$adminNotices->add_admin_notice( $slug, $config );

		$notices = $adminNotices->get_admin_notices();
		$this->assertArrayHasKey( $slug, $notices );
		$this->assertSame( $config, $notices[ $slug ] );
	}

	/**
	 * Test adding and retrieving admin notices.
	 */
	public function testGetAdminNotices(): void {
		$adminNotices = AdminNotices::get_instance();

		$slug   = 'test-notice';
		$config = [
			'message'        => 'Test Notice Message',
			'type'           => 'warning',
			'is_dismissable' => true,
		];

		$adminNotices->add_admin_notice( $slug, $config );

		$notices = $adminNotices->get_admin_notices();
		$this->assertArrayHasKey( $slug, $notices );
		$this->assertSame( $config, $notices[ $slug ] );

		$get_admin_notices = get_graphql_admin_notices();

		$this->assertSame( $get_admin_notices, $notices );
	}

	/**
	 * Test removing admin notices.
	 */
	public function testRemoveAdminNotices(): void {
		$adminNotices = AdminNotices::get_instance();

		$slug   = 'test-notice';
		$config = [ 'message' => 'Test Notice Message' ];

		$adminNotices->add_admin_notice( $slug, $config );
		$adminNotices->remove_admin_notice( $slug );

		$notices = $adminNotices->get_admin_notices();
		$this->assertArrayNotHasKey( $slug, $notices );
	}
}
