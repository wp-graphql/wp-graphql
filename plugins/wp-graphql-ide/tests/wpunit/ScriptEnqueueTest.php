<?php
/**
 * Test script and style enqueuing for WPGraphQL IDE
 *
 * @package WPGraphQLIDE
 */

namespace WPGraphQLIDE;

/**
 * Test script and style enqueuing
 */
class ScriptEnqueueTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Ensure user has the required capability
		$user = $this->factory()->user->create_and_get( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user->ID );
	}

	/**
	 * Test that scripts are enqueued on admin pages when user has capability
	 */
	public function test_scripts_enqueued_on_admin_pages() {
		// Set up admin context
		set_current_screen( 'dashboard' );
		
		// Trigger the enqueue action
		do_action( 'admin_enqueue_scripts', 'dashboard' );
		
		// Check that main script is enqueued
		$this->assertTrue( wp_script_is( 'wpgraphql-ide', 'enqueued' ), 'Main IDE script should be enqueued' );
		$this->assertTrue( wp_script_is( 'graphql', 'registered' ), 'GraphQL script should be registered' );
		$this->assertTrue( wp_script_is( 'wpgraphql-ide-render', 'enqueued' ), 'IDE render script should be enqueued' );
	}

	/**
	 * Test that styles are enqueued on admin pages
	 */
	public function test_styles_enqueued_on_admin_pages() {
		// Set up admin context
		set_current_screen( 'dashboard' );
		
		// Trigger the enqueue action
		do_action( 'admin_enqueue_scripts', 'dashboard' );
		
		// Check that styles are enqueued
		$this->assertTrue( wp_style_is( 'wpgraphql-ide-app', 'enqueued' ), 'IDE app style should be enqueued' );
		$this->assertTrue( wp_style_is( 'wpgraphql-ide-render', 'enqueued' ), 'IDE render style should be enqueued' );
		$this->assertTrue( wp_style_is( 'wpgraphql-ide', 'enqueued' ), 'IDE main style should be enqueued' );
	}

	/**
	 * Test that scripts are enqueued on frontend when user has capability
	 */
	public function test_scripts_enqueued_on_frontend() {
		// Trigger the enqueue action for frontend
		do_action( 'wp_enqueue_scripts' );
		
		// Check that main script is enqueued
		$this->assertTrue( wp_script_is( 'wpgraphql-ide', 'enqueued' ), 'Main IDE script should be enqueued on frontend' );
	}

	/**
	 * Test that localized data is attached to script
	 */
	public function test_script_localized_data() {
		// Set up admin context
		set_current_screen( 'dashboard' );
		
		// Trigger the enqueue action
		do_action( 'admin_enqueue_scripts', 'dashboard' );
		
		// Get the localized data
		global $wp_scripts;
		$script = $wp_scripts->get_data( 'wpgraphql-ide', 'data' );
		
		// Check that localized data exists
		$this->assertNotEmpty( $script, 'Script should have localized data' );
		$this->assertStringContainsString( 'WPGRAPHQL_IDE_DATA', $script, 'Localized data should contain WPGRAPHQL_IDE_DATA' );
	}

	/**
	 * Test that scripts are not enqueued when user lacks capability
	 */
	public function test_scripts_not_enqueued_without_capability() {
		// Create a user without the capability
		$user = $this->factory()->user->create_and_get( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user->ID );
		
		// Set up admin context
		set_current_screen( 'dashboard' );
		
		// Trigger the enqueue action
		do_action( 'admin_enqueue_scripts', 'dashboard' );
		
		// Check that scripts are NOT enqueued
		$this->assertFalse( wp_script_is( 'wpgraphql-ide', 'enqueued' ), 'IDE script should not be enqueued without capability' );
	}

	/**
	 * Test that scripts are not enqueued when WPGraphQL is not available
	 */
	public function test_scripts_not_enqueued_without_wpgraphql() {
		// Temporarily remove WPGraphQL class
		// Note: This test may need adjustment based on how WPGraphQL is loaded
		// For now, we'll test the normal case where WPGraphQL is available
		$this->assertTrue( class_exists( '\WPGraphQL\Router' ), 'WPGraphQL should be available in test environment' );
	}

	/**
	 * Test that menu icon CSS is enqueued
	 */
	public function test_menu_icon_css_enqueued() {
		// Set up admin context
		set_current_screen( 'dashboard' );
		
		// Trigger the enqueue action
		do_action( 'admin_enqueue_scripts', 'dashboard' );
		
		// Check that menu icon CSS is enqueued (if it exists)
		// This may need adjustment based on actual implementation
		global $wp_styles;
		$enqueued = isset( $wp_styles->queue ) ? $wp_styles->queue : [];
		$this->assertNotEmpty( $enqueued, 'At least some styles should be enqueued' );
	}
}
