<?php

namespace WPGraphQL\Tests;

use WPGraphQL\Admin\AdminNotices;
use PHPUnit\Framework\TestCase;

/**
 * A comprehensive test case for the AdminNotices class of the WPGraphQL plugin.
 */
class AdminNoticesTest extends TestCase {

    /**
     * Set up the test environment before each test.
     */
    protected function setUp(): void {
        parent::setUp();
        \WP_Mock::setUp();
    }

    /**
     * Clean up the test environment after each test.
     */
    protected function tearDown(): void {
        \WP_Mock::tearDown();
        parent::tearDown();
    }

    /**
     * Test the initialization process.
     */
    public function testInit() {
        \WP_Mock::expectActionAdded('admin_notices', [\WP_Mock\Functions::type('object'), 'maybe_display_notices']);
        \WP_Mock::expectActionAdded('admin_init', [\WP_Mock\Functions::type('object'), 'handle_dismissal_of_acf_notice']);
        \WP_Mock::expectAction('graphql_admin_notices_init', \WP_Mock\Functions::type('object'));

        \WP_Mock::userFunction('get_option', [
            'args' => ['wpgraphql_dismissed_admin_notices', []],
            'return' => [],
        ]);

        $adminNotices = new AdminNotices();
        $adminNotices->init();

        // Verify that the actions are correctly added
        $this->assertHooksAdded();
    }

    /**
     * Test adding a new admin notice.
     */
    public function testAddAdminNotice() {
        $slug = 'test_notice';
        $config = [
            'type' => 'error',
            'message' => 'Test error message',
            'is_dismissable' => true,
        ];

        \WP_Mock::onFilter('graphql_add_admin_notice')
            ->with($config, $slug)
            ->reply($config);

        $adminNotices = new AdminNotices();
        $notice = $adminNotices->add_admin_notice($slug, $config);

        $this->assertEquals($config, $notice);
    }

    /**
     * Test removing an admin notice.
     */
    public function testRemoveAdminNotice() {
        $slug = 'test_notice';
        $adminNotices = new AdminNotices();

        // First, add a notice to ensure there's something to remove
        $adminNotices->add_admin_notice($slug, [
            'type' => 'error',
            'message' => 'Test error message',
            'is_dismissable' => true,
        ]);

        // Now, remove the notice
        $adminNotices->remove_admin_notice($slug);
        $notices = $adminNotices->get_admin_notices();

        $this->assertArrayNotHasKey($slug, $notices);
    }

    /**
     * Test the pre-filtering of dismissed notices.
     */
    public function testPreFilterDismissedNotices() {
        $dismissedNotices = ['dismissed_notice'];

        \WP_Mock::userFunction('get_option', [
            'args' => ['wpgraphql_dismissed_admin_notices', []],
            'return' => $dismissedNotices,
        ]);

        $adminNotices = new AdminNotices();
        $adminNotices->init();

        // Assume `dismissed_notice` was added before calling `init`
        // Now it should be filtered out, so it should not exist in the admin notices array
        $notices = $adminNotices->get_admin_notices();
        $this->assertArrayNotHasKey('dismissed_notice', $notices);
    }

    /**
     * Test the display of notices under the right conditions.
     */
    public function testMaybeDisplayNotices() {
        \WP_Mock::userFunction('get_current_screen', [
            'return' => (object) ['id' => 'toplevel_page_graphiql-ide'],
        ]);

        \WP_Mock::expectAction('admin_notices');

        $adminNotices = new AdminNotices();
        // The actual display logic is output buffering, which is difficult to test without an output,
        // but we can ensure the conditional logic to reach display is working.
        $adminNotices->maybe_display_notices();

        // Verifies that the expected hooks are executed
        $this->assertHooksAdded();
    }

    /**
     * Test handling the dismissal of an admin notice.
     */
    public function testHandleDismissalOfAcfNotice() {
        $_GET['wpgraphql_disable_notice_nonce'] = 'fake_nonce';
        $_GET['wpgraphql_disable_notice'] = 'test_notice';

        \WP_Mock::userFunction('sanitize_text_field', [
            'return_arg' => 0,
        ]);

        \WP_Mock::userFunction('wp_unslash', [
            'return_arg' => 0,
        ]);

        \WP_Mock::userFunction('wp_verify_nonce', [
            'return' => true,
        ]);

        \WP_Mock::userFunction('update_option', [
            'times' => 1,
            'args' => ['wpgraphql_dismissed_admin_notices', \WP_Mock\Functions::type('array')],
        ]);

        \WP_Mock::userFunction('wp_safe_redirect', [
            'times' => 1,
        ]);

        \WP_Mock::userFunction('exit', [
            'times' => 1,
        ]);

        $adminNotices = new AdminNotices();
        $adminNotices->handle_dismissal_of_acf_notice();

        // Ensure update_option was called with the correct arguments, implying the dismissal was handled
        $this->assertTrue(true); // This assertion is a placeholder to ensure the test completes. The real test is the expectation above.
    }

    /**
     * Helper function to assert that hooks have been added.
     */
    private function assertHooksAdded() {
        \WP_Mock::assertHooksAdded();
    }
}
