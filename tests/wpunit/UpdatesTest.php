<?php

use WPGraphQL\Admin\Updates\Updates;

class UpdatesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {
	private $test_plugins = [];
	private $old_current_screen;

	/**
	 * {@inheritdoc}
	 */
	public function setUp(): void {
		global $current_screen;
		$this->old_current_screen = $current_screen;

		$this->test_plugins = [
			'plugin-with-headers',
			'plugin-with-requires',
			'plugin-with-meta',
			'plugin-incompatible-version',
		];

		// Cleanup any test plugins that may have been left behind.
		foreach ( $this->test_plugins as $plugin ) {
			$this->cleanup_test_plugin( $plugin );
		}

		parent::setUp();

		wp_cache_delete( 'plugins', 'plugins' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function tearDown(): void {
		// Remove the test plugin from the plugins directory.
		foreach ( $this->test_plugins as $plugin ) {
			$this->cleanup_test_plugin( $plugin );
		}

		wp_cache_delete( 'plugins', 'plugins' );

		$this->reset_current_screen();

		parent::tearDown();
	}

	private function install_test_plugin( string $plugin ) {
		$plugin_dir = WP_PLUGIN_DIR . '/' . $plugin;
		mkdir( $plugin_dir );

		$plugin_file = $plugin_dir . '/' . $plugin . '.php';
		copy( codecept_data_dir() . 'plugins/' . $plugin . '.php', $plugin_file );
	}

	private function cleanup_test_plugin( string $plugin ) {
		$plugin_dir = WP_PLUGIN_DIR . '/' . $plugin;

		@unlink( $plugin_dir . '/' . $plugin . '.php' );
		@rmdir( $plugin_dir );

		$this->deactivate_plugin( $plugin . '/' . $plugin . '.php' );
	}


	/**
	 * Test plugin headers.
	 */
	public function testPluginHeaders(): void {
		$actual = get_plugins();

		$this->assertArrayHasKey( 'wp-graphql/wp-graphql.php', $actual, 'WPGraphQL Plugin not found.' );

		$actual_plugin_data = $actual['wp-graphql/wp-graphql.php'];
		$this->assertArrayHasKey( 'Requires WPGraphQL', $actual_plugin_data, 'Requires WPGraphQL header not found.' );
		$this->assertArrayHasKey( 'WPGraphQL tested up to', $actual_plugin_data, 'WPGraphQL tested up to header not found.' );
	}

	/**
	 * Test load_screen_checker does not throw an error.
	 */
	public function testLoadScreenChecker(): void {
		$updates = new Updates();
		$updates->init();

		// Test against a non-plugin screen.
		set_current_screen( 'test' );

		$updates->load_screen_checker();

		// Test against the plugins screen.
		set_current_screen( 'plugins' );
		$updates->load_screen_checker();

		// Test against the updates screen.
		set_current_screen( 'update-core' );
		$updates->load_screen_checker();

		$this->assertTrue( true, 'load_screen_checker did not throw an error.' );
	}

	/**
	 * Test register_assets enqueues the correct styles.
	 */
	public function testRegisterAssets(): void {
		$updates = new Updates();
		$updates->init();

		// On a non-allowed screen.
		$this->reset_current_screen();
		$updates->register_assets();

		$this->assertArrayNotHasKey( 'wp-graphql-admin-updates', wp_styles()->registered, 'wp-graphql-admin-updates style should not be registered.' );

		// On the plugins screen.
		set_current_screen( 'plugins' );
		$updates->register_assets();

		$actual = wp_styles()->registered['wp-graphql-admin-updates'];

		$this->assertArrayHasKey( 'wp-graphql-admin-updates', wp_styles()->registered, 'wp-graphql-admin-updates style not registered.' );
		$this->assertStringContainsString( 'wp-graphql/build/updates.css', $actual->src, 'wp-graphql-admin-updates style not enqueued.' );

		// On the updates screen.
		set_current_screen( 'update-core' );
		$updates->register_assets();

		$actual = wp_styles()->registered['wp-graphql-admin-updates'];

		$this->assertArrayHasKey( 'wp-graphql-admin-updates', wp_styles()->registered, 'wp-graphql-admin-updates style not registered.' );
		$this->assertStringContainsString( 'wp-graphql/build/updates.css', $actual->src, 'wp-graphql-admin-updates style not enqueued.' );
	}

	/**
	 * Test whether maybe_allow_autoupdates returns the correct value.
	 */
	public function testMaybeAllowAutoupdates(): void {
		$updates = new Updates();
		$updates->init();

		// Test Against a non-WPGraphQL plugin.
		$plugin_data = (object) [
			'plugin'      => 'test-plugin/test-plugin.php',
			'new_version' => '1.0.0',
		];

		$default_autoupdate = false;
		$actual             = $updates->maybe_allow_autoupdates( $default_autoupdate, $plugin_data );

		$this->assertEquals( $default_autoupdate, $actual, 'maybe_allow_autoupdates did not return the the default value.' );

		$default_autoupdate = true;
		$actual             = $updates->maybe_allow_autoupdates( $default_autoupdate, $plugin_data );

		$this->assertEquals( $default_autoupdate, $actual, 'maybe_allow_autoupdates did not return the default value.' );

		// Test Against WPGraphQL with no new version.
		$plugin_data = (object) [
			'plugin'      => 'wp-graphql/wp-graphql.php',
			'new_version' => '',
		];

		$default_autoupdate = false;
		$actual             = $updates->maybe_allow_autoupdates( $default_autoupdate, $plugin_data );

		$this->assertEquals( $default_autoupdate, $actual, 'maybe_allow_autoupdates did not return the the default value.' );

		$default_autoupdate = true;
		$actual             = $updates->maybe_allow_autoupdates( $default_autoupdate, $plugin_data );

		$this->assertEquals( $default_autoupdate, $actual, 'maybe_allow_autoupdates did not return the default value.' );

		// Test with a minor update.
		$current_version = explode( '.', WPGRAPHQL_VERSION );
		$plugin_data     = $this->get_plugin_update_data( $current_version[0] . '.99.0' );
		$actual          = $updates->maybe_allow_autoupdates( $default_autoupdate, $plugin_data );

		// Test with a major update.
		$plugin_data = $this->get_plugin_update_data( '99.0.0' );

		$default_autoupdate = true;
		$actual             = $updates->maybe_allow_autoupdates( $default_autoupdate, $plugin_data );

		$this->assertFalse( $actual, 'maybe_allow_autoupdates should return false for a major update.' );

		// Test with major updates enabled.
		add_filter( 'wpgraphql_enable_major_autoupdates', '__return_true' );

		$actual = $updates->maybe_allow_autoupdates( $default_autoupdate, $plugin_data );

		$this->assertTrue( $actual, 'maybe_allow_autoupdates should return true.' );

		// Cleanup.
		remove_filter( 'wpgraphql_enable_major_autoupdates', '__return_true' );
	}

	/**
	 * Tests disabling incompatible plugins.
	 */
	public function testDisableIncompatiblePlugins(): void {
		// Codeception doesn't deactivate plugins so we check if the hook was triggered.
		$deactivated_plugin = false;
		add_action(
			'deactivate_plugin',
			static function ( $plugin ) use ( &$deactivated_plugin ) {
				if ( 'plugin-incompatible-version/plugin-incompatible-version.php' === $plugin ) {
					$deactivated_plugin = true;
				}
			}
		);

		$updates = new Updates();
		$updates->init();

		// Test with no plugins installed.
		$updates->disable_incompatible_plugins();

		$this->assertFalse( $deactivated_plugin, 'Plugin should not exist.' );

		// Test with an installed plugin.
		$this->install_test_plugin( 'plugin-incompatible-version' );
		wp_cache_delete( 'plugins', 'plugins' );

		$updates->disable_incompatible_plugins();

		$this->assertFalse( $deactivated_plugin, 'Plugin should already be deactivated.' );

		// Test wih an active plugin.
		$this->activate_plugin( 'plugin-incompatible-version/plugin-incompatible-version.php' );

		// Confirm the plugin is active.
		$this->assertTrue( is_plugin_active( 'plugin-incompatible-version/plugin-incompatible-version.php' ), 'Plugin is not active.' );

		$updates->disable_incompatible_plugins();

		$this->assertTrue( $deactivated_plugin, 'Plugin was not deactivated.' );

		$actual_data = get_transient( 'wpgraphql_incompatible_plugins' );

		$this->assertNotEmpty( $actual_data, 'Transient was not set.' );

		ob_start();
		do_action( 'admin_notices' );
		$message = ob_get_clean();

		$this->assertStringContainsString( 'The following plugins were deactivated', $message, 'Deactivation message not found.' );
		$this->assertStringContainsString( 'Incompatible Version', $message, 'Deactivated plugin not found.' );

		// Ensure transient is cleared.
		$actual_data = get_transient( 'wpgraphql_incompatible_plugins' );

		$this->assertEmpty( $actual_data, 'Transient was not cleared.' );

		// Cleanup.
		$this->cleanup_test_plugin( 'plugin-incompatible-version' );
		remove_all_actions( 'deactivate_plugin' );
	}


	/**
	 * Test updating with a `Requires Plugin` dependency.
	 */
	public function testUpdateWithRequiresPluginDep(): void {
		// Only test on WP 6.5+.
		if ( ! is_wp_version_compatible( '6.5' ) ) {
			$this->markTestSkipped( 'Requires Plugin header does not exist prior to < 6.5.' );
		}

		$this->install_test_plugin( 'plugin-with-requires' );
		wp_cache_delete( 'plugins', 'plugins' );

		$updates = new Updates();
		$updates->init();

		add_filter( 'wpgraphql_enable_major_autoupdates', '__return_true' );

		$plugin_date = $this->get_plugin_update_data( '99.0.0' );

		$actual = $updates->maybe_allow_autoupdates( true, $plugin_date );

		$this->assertFalse( $actual, 'maybe_allow_autoupdates should return false for a plugin with a `Requires Plugin` dependency.' );

		// Cleanup.
		$this->cleanup_test_plugin( 'plugin-with-requires' );
		remove_filter( 'wpgraphql_enable_major_autoupdates', '__return_true' );
	}

	/**
	 * Test updating with a `Tested up to` dependency.
	 */
	public function testUpdateWithHeadersDep(): void {
		$this->install_test_plugin( 'plugin-with-headers' );
		wp_cache_delete( 'plugins', 'plugins' );

		$updates = new Updates();
		$updates->init();

		add_filter( 'wpgraphql_enable_major_autoupdates', '__return_true' );

		// Test with a major update.
		$plugin_date = $this->get_plugin_update_data( '99.0.0' );

		$actual = $updates->maybe_allow_autoupdates( true, $plugin_date );

		$this->assertFalse( $actual, 'maybe_allow_autoupdates should return false for a plugin with a `Tested up to` dependency.' );

		// Test with a minor update.
		$current_version = explode( '.', WPGRAPHQL_VERSION );
		$plugin_data     = $this->get_plugin_update_data( $current_version[0] . '.99.0' );

		$actual = $updates->maybe_allow_autoupdates( true, $plugin_data );

		$this->assertTrue( $actual, 'maybe_allow_autoupdates should return true for a minor update.' );

		// Test with untested autoupdates disabled.
		add_filter(
			'wpgraphql_untested_release_type',
			static function () {
				return 'minor';
			}
		);

		$actual = $updates->maybe_allow_autoupdates( true, $plugin_data );

		$this->assertFalse( $actual, 'maybe_allow_autoupdates should return false for a minor update if disallowed.' );

		// Test with a patch update.
		remove_all_filters( 'wpgraphql_untested_release_type' );
		$plugin_data = $this->get_plugin_update_data( $current_version[0] . '.0.99' );

		$actual = $updates->maybe_allow_autoupdates( true, $plugin_data );

		$this->assertTrue( $actual, 'maybe_allow_autoupdates should return true for a patch.' );

		// Test with autoupdates disabled.
		add_filter(
			'wpgraphql_untested_release_type',
			static function () {
				return 'patch';
			}
		);

		$actual = $updates->maybe_allow_autoupdates( true, $plugin_data );

		$this->assertFalse( $actual, 'maybe_allow_autoupdates should return false for a patch if disallowed.' );

		// Cleanup.
		$this->cleanup_test_plugin( 'plugin-with-headers' );
		remove_filter( 'wpgraphql_enable_major_autoupdates', '__return_true' );
		remove_all_filters( 'wpgraphql_untested_release_type' );
	}

	/**
	 * Test updating with a possible dependency.
	 */
	public function testUpdateWithPossibleDep(): void {
		$this->install_test_plugin( 'plugin-with-meta' );
		wp_cache_delete( 'plugins', 'plugins' );

		$updates = new Updates();
		$updates->init();

		add_filter( 'wpgraphql_enable_major_autoupdates', '__return_true' );

		// Test with a major update.
		$plugin_date = $this->get_plugin_update_data( '99.0.0' );

		$actual = $updates->maybe_allow_autoupdates( true, $plugin_date );

		$this->assertFalse( $actual, 'maybe_allow_autoupdates should return false for a plugin with a possible dependency.' );

		// Cleanup.
		$this->cleanup_test_plugin( 'plugin-with-meta' );
		remove_filter( 'wpgraphql_enable_major_autoupdates', '__return_true' );
	}

	/**
	 * Test the Plugin Screen inline messsage and modal.
	 */
	public function testPluginScreenLoader(): void {
		$this->install_test_plugin( 'plugin-with-headers' );
		$this->activate_plugin( 'plugin-with-headers/plugin-with-headers.php' );

		$loader = new \WPGraphQL\Admin\Updates\PluginsScreenLoader();

		ob_start();
		$loader->in_plugin_update_message( [], (object) [ 'new_version' => '99.0.0' ] );
		$message = ob_get_clean();

		// Test the modal message.
		$this->assertStringContainsString( 'The following active plugin(s) require WPGraphQL to function but have not yet declared compatibility with <strong>WPGraphQL v99.0.0</strong>', $message, 'Plugin screen message not found.' );
		// Test the plugin name.
		$this->assertStringContainsString( 'Test Plugin With Headers', $message, 'Plugin name not found.' );

		// Ensure the modal js is output.
		ob_start();
		$loader->modal_js();
		$expected = ob_get_clean();

		ob_start();
		do_action( 'admin_print_footer_scripts' );
		$actual = ob_get_clean();

		$this->assertStringContainsString( $expected, $actual, 'Modal JS not output.' );

		// Cleanup.
		$this->cleanup_test_plugin( 'plugin-with-headers' );
	}

	/**
	 * Test the update screen modal.
	 */
	public function testUpdateScreenLoader(): void {
		$this->install_test_plugin( 'plugin-with-headers' );
		$this->activate_plugin( 'plugin-with-headers/plugin-with-headers.php' );

		$loader = new \WPGraphQL\Admin\Updates\UpdatesScreenLoader();

		// Test with no plugin update.
		ob_start();
		$loader->update_screen_modal();
		$message = ob_get_clean();

		$this->assertEmpty( $message, 'Plugin screen message should be empty.' );

		// Stub a plugin update.
		add_filter(
			'pre_site_transient_update_plugins',
			function () {
				return (object) [
					'response' => [
						'wp-graphql/wp-graphql.php' => $this->get_plugin_update_data( '99.0.0' ),
					],
				];
			},
			PHP_INT_MAX
		);

		ob_start();
		$loader->update_screen_modal();
		$message = ob_get_clean();

		$this->assertStringContainsString( 'The following active plugin(s) require WPGraphQL to function but have not yet declared compatibility with <strong>WPGraphQL v99.0.0</strong', $message, 'Plugin screen message not found.' );
		$this->assertStringContainsString( 'Test Plugin With Headers', $message, 'Plugin name not found.' );

		// Cleanup.
		$this->cleanup_test_plugin( 'plugin-with-headers' );
		remove_all_filters( 'pre_site_transient_update_plugins' );
	}

	/**
	 * Test updating with an incompatible plugin.
	 */
	public function testUpdateWithIncompatiblePlugin(): void {
		$this->install_test_plugin( 'plugin-incompatible-version' );

		add_filter( 'wpgraphql_enable_major_autoupdates', '__return_true' );

		// Test with a deactivated incompatible version.
		$updates = new Updates();
		$updates->init();

		$plugin_data = $this->get_plugin_update_data( '90.0.0' );

		$actual = $updates->maybe_allow_autoupdates( true, $plugin_data );

		$this->assertTrue( $actual, 'maybe_allow_autoupdates should return true if the incompatible plugin is deactivated.' );

		// Test with an active incompatible version.
		$this->activate_plugin( 'plugin-incompatible-version/plugin-incompatible-version.php' );

		$updates = new Updates();
		$updates->init();

		$actual = $updates->maybe_allow_autoupdates( true, $plugin_data );

		$this->assertFalse( $actual, 'maybe_allow_autoupdates should return false if the incompatible plugin is active.' );

		// Test with an incompatible minor update.
		$updates = new Updates();
		$updates->init();

		$plugin_data = $this->get_plugin_update_data( '99.0.0' );

		$actual = $updates->maybe_allow_autoupdates( true, $plugin_data );

		$this->assertFalse( $actual, 'maybe_allow_autoupdates should return false if the incompatible plugin is active.' );

		// Test with an incompatible patch update.
		$plugin_data = $this->get_plugin_update_data( '99.2.0' );

		$actual = $updates->maybe_allow_autoupdates( true, $plugin_data );

		$this->assertFalse( $actual, 'maybe_allow_autoupdates should return false if the incompatible plugin is active.' );

		// Test with compatible minor update.
		$updates = new Updates();
		$updates->init();

		$plugin_data = $this->get_plugin_update_data( '99.99.99' );

		$actual = $updates->maybe_allow_autoupdates( true, $plugin_data );

		$this->assertTrue( $actual, 'maybe_allow_autoupdates should return true since the plugin is compatible.' );

		// Cleanup.
		$this->cleanup_test_plugin( 'plugin-incompatible-version' );
		remove_filter( 'wpgraphql_enable_major_autoupdates', '__return_true' );
	}

	/**
	 * Set plugin update data.
	 *
	 * @var string $version The plugin version.
	 */
	private function get_plugin_update_data( string $version ) {
		return (object) [
			'slug'        => 'wp-graphql',
			'plugin'      => 'wp-graphql/wp-graphql.php',
			'new_version' => $version,
		];
	}

	/**
	 * Activates a plugin based on its dir/filename
	 *
	 * @param string $plugin
	 */
	private function activate_plugin( $plugin ): void {
		$current = get_option( 'active_plugins' );
		if ( ! in_array( $plugin, $current, true ) ) {
			$current[] = $plugin;
			sort( $current );
			$success = update_option( 'active_plugins', $current );

			$GLOBALS['wp_tests_options']['active_plugins'] = $current;

			if ( ! $success ) {
				codecept_debug( 'Failed to activate plugin.' );
			}
		}
	}

	/**
	 * Deactivates a plugin based on its dir/filename
	 *
	 * @param string $plugin
	 */
	private function deactivate_plugin( $plugin ): void {
		$current = get_option( 'active_plugins' );
		if ( in_array( $plugin, $current, true ) ) {
			$current = array_diff( $current, [ $plugin ] );
			sort( $current );
			$success = update_option( 'active_plugins', $current );

			$GLOBALS['wp_tests_options']['active_plugins'] = $current;

			if ( ! $success ) {
				codecept_debug( 'Failed to deactivate plugin.' );
			}
		}
	}

	/**
	 * Reset the current screen.
	 */
	private function reset_current_screen(): void {
		global $current_screen;
		$current_screen = $this->old_current_screen;
	}
}
