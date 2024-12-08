<?php

use WPGraphQL\Admin\Updates\Updates;

class UpdatesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {
	private $test_plugins = [];

	/**
	 * {@inheritdoc}
	 */
	public function setUp(): void {

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
		// Test against a non-plugin screen.
		set_current_screen( 'test' );
		$updates = new Updates();
		$updates->init();

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
}
