<?php

namespace WPGraphQL\Tests;

use WPGraphQL\Admin\AdminNotices;
use PHPUnit\Framework\TestCase;

/**
 * Class AdminNoticesTest
 *
 * Tests the AdminNotices class functionality within the WPGraphQL plugin.
 */
class AdminNoticesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		// Mock WordPress functions like add_action, do_action, etc., here if necessary
		// This setup depends on the testing environment you have (plain PHPUnit, WordPress PHPUnit, etc.)
	}

	/**
	 * Test initialization of admin notices.
	 */
	public function testInit() {
		$adminNotices = new AdminNotices();
		$adminNotices->init();

		// Assertions to ensure actions are hooked correctly
		// This might require functional testing or integration testing setup
		$this->assertTrue( has_action('admin_notices' ) );
	}

	/**
	 * Test adding and retrieving admin notices.
	 */
	public function testAddAndGetAdminNotices() {
		$adminNotices = new AdminNotices();

		$slug = 'test-notice';
		$config = [
			'message' => 'Test Notice Message',
			'type' => 'warning',
			'is_dismissable' => true
		];

		$adminNotices->add_admin_notice($slug, $config);

		$notices = $adminNotices->get_admin_notices();
		$this->assertArrayHasKey($slug, $notices);
		$this->assertSame($config, $notices[$slug]);
	}

	/**
	 * Test removing admin notices.
	 */
	public function testRemoveAdminNotices() {
		$adminNotices = new AdminNotices();

		$slug = 'test-notice';
		$config = ['message' => 'Test Notice Message'];

		$adminNotices->add_admin_notice($slug, $config);
		$adminNotices->remove_admin_notice($slug);

		$notices = $adminNotices->get_admin_notices();
		$this->assertArrayNotHasKey($slug, $notices);
	}

	protected function tearDown(): void {
		parent::tearDown();
		// Clean up your mocks and any other global state changes here
	}
}
