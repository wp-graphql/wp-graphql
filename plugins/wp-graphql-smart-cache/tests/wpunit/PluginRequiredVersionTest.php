<?php
/**
 * Class PluginRequiredVersionTest
 *
 * @package Wp_Graphql_Smart_Cache
 */

namespace WPGraphQL\SmartCache;

/**
 * The minimum WPGraphQL version is declared twice: in the "Requires WPGraphQL"
 * plugin header, which WordPress reads for its dependency checks, and in the
 * WPGRAPHQL_SMART_CACHE_WPGRAPHQL_REQUIRED_MIN_VERSION constant that
 * can_load_plugin() uses at runtime. The header said 2.0.0 while the constant had
 * drifted to 1.12.0, so can_load_plugin() let WPGraphQL 1.x through without the
 * admin notice.
 *
 * The fatal reported in wp-graphql/wp-graphql#4297 is covered separately by
 * SettingsCacheInvalidationTest.
 */
class PluginRequiredVersionTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * The runtime gate must stay in sync with the declared header, or can_load_plugin() lets an
	 * incompatible core version through instead of showing the admin notice.
	 */
	public function test_required_min_version_constant_matches_declared_header(): void {
		$plugin_file = WPGRAPHQL_SMART_CACHE_PLUGIN_DIR . 'wp-graphql-smart-cache.php';
		$this->assertFileExists( $plugin_file );

		// Parse the header the same way WordPress does when it checks plugin dependencies.
		$headers = get_file_data( $plugin_file, [ 'RequiresWPGraphQL' => 'Requires WPGraphQL' ] );
		$this->assertNotEmpty( $headers['RequiresWPGraphQL'], 'Plugin file must declare a "Requires WPGraphQL" header.' );

		$this->assertSame(
			$headers['RequiresWPGraphQL'],
			WPGRAPHQL_SMART_CACHE_WPGRAPHQL_REQUIRED_MIN_VERSION,
			'WPGRAPHQL_SMART_CACHE_WPGRAPHQL_REQUIRED_MIN_VERSION must match the "Requires WPGraphQL" header.'
		);
	}

	/**
	 * With the currently active (compatible) WPGraphQL core, can_load_plugin() should return true.
	 */
	public function test_can_load_plugin_returns_true_with_compatible_core(): void {
		$this->assertTrue( can_load_plugin() );
	}
}
