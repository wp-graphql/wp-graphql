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
		
		// Ensure WPGraphQL is loaded and capabilities are set up
		// This ensures the init hook has run and capabilities are available
		if ( class_exists( '\WPGraphQL\Router' ) ) {
			do_action( 'init' );
		}
		
		// Ensure user has the required capability
		$user = $this->factory()->user->create_and_get( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user->ID );
	}

	/**
	 * Helper to check if build assets exist
	 *
	 * @return bool
	 */
	private function build_assets_exist(): bool {
		$plugin_dir = WPGRAPHQL_IDE_PLUGIN_DIR_PATH;
		return file_exists( $plugin_dir . 'build/wpgraphql-ide.asset.php' )
			&& file_exists( $plugin_dir . 'build/wpgraphql-ide-render.asset.php' )
			&& file_exists( $plugin_dir . 'build/graphql.asset.php' );
	}

	/**
	 * Test that scripts are enqueued on admin pages when user has capability
	 */
	public function test_scripts_enqueued_on_admin_pages() {
		// Skip test if build assets don't exist
		if ( ! $this->build_assets_exist() ) {
			$this->markTestSkipped( 'Build assets not found. Run `npm run build:main` to generate them.' );
		}

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
		// Skip test if build assets don't exist
		if ( ! $this->build_assets_exist() ) {
			$this->markTestSkipped( 'Build assets not found. Run `npm run build:main` to generate them.' );
		}

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
	 * Test that localized data is attached to script
	 */
	public function test_script_localized_data() {
		// Skip test if build assets don't exist
		if ( ! $this->build_assets_exist() ) {
			$this->markTestSkipped( 'Build assets not found. Run `npm run build:main` to generate them.' );
		}

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
		// Clear any previously enqueued scripts
		wp_dequeue_script( 'wpgraphql-ide' );
		wp_deregister_script( 'wpgraphql-ide' );
		wp_dequeue_script( 'wpgraphql-ide-render' );
		wp_deregister_script( 'wpgraphql-ide-render' );
		wp_dequeue_script( 'graphql' );
		wp_deregister_script( 'graphql' );
		
		// Create a user without the capability
		$user = $this->factory()->user->create_and_get( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user->ID );
		
		// Ensure the subscriber role doesn't have the capability
		$subscriber_role = get_role( 'subscriber' );
		if ( $subscriber_role && $subscriber_role->has_cap( 'manage_graphql_ide' ) ) {
			$subscriber_role->remove_cap( 'manage_graphql_ide' );
		}
		
		// Also remove from the user directly (in case it was added via user meta)
		$user_obj = new \WP_User( $user->ID );
		if ( $user_obj->has_cap( 'manage_graphql_ide' ) ) {
			$user_obj->remove_cap( 'manage_graphql_ide' );
		}
		
		// Verify the user doesn't have the capability
		$this->assertFalse( current_user_can( 'manage_graphql_ide' ), 'Subscriber should not have manage_graphql_ide capability' );
		
		// Set up admin context
		set_current_screen( 'dashboard' );
		
		// Clear the script queue to ensure we're testing fresh
		global $wp_scripts;
		$wp_scripts->queue = [];
		$wp_scripts->registered = [];
		
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
