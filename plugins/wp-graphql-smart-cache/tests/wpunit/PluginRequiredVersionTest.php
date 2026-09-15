<?php
/**
 * Class PluginRequiredVersionTest
 *
 * @package Wp_Graphql_Smart_Cache
 */

namespace WPGraphQL\SmartCache;

/**
 * Regression test for wp-graphql/wp-graphql#4297.
 *
 * The plugin header and readme.txt declare "Requires WPGraphQL: 2.0.0", but the runtime
 * compatibility gate (WPGRAPHQL_SMART_CACHE_WPGRAPHQL_REQUIRED_MIN_VERSION) had drifted and
 * stayed hardcoded at an older floor. A site running a WPGraphQL core version between the two
 * numbers passed can_load_plugin() and never saw the "please update WPGraphQL" admin notice,
 * so the plugin fully initialized against a core it wasn't actually compatible with and fataled.
 */
class PluginRequiredVersionTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * The runtime gate must stay in sync with the declared header, or can_load_plugin() lets an
	 * incompatible core version through instead of showing the admin notice.
	 */
	public function test_required_min_version_constant_matches_declared_header(): void {
		$plugin_file = WPGRAPHQL_SMART_CACHE_PLUGIN_DIR . 'wp-graphql-smart-cache.php';
		$this->assertFileExists( $plugin_file );

		$contents = (string) file_get_contents( $plugin_file );
		$matched  = preg_match( '/Requires WPGraphQL:\s*([0-9.]+)/', $contents, $matches );
		$this->assertSame( 1, $matched, 'Plugin file must declare a "Requires WPGraphQL" header.' );

		$this->assertSame(
			$matches[1],
			WPGRAPHQL_SMART_CACHE_WPGRAPHQL_REQUIRED_MIN_VERSION,
			'WPGRAPHQL_SMART_CACHE_WPGRAPHQL_REQUIRED_MIN_VERSION must match the "Requires WPGraphQL" header (wp-graphql/wp-graphql#4297).'
		);
	}

	/**
	 * With the currently active (compatible) WPGraphQL core, can_load_plugin() should return true.
	 */
	public function test_can_load_plugin_returns_true_with_compatible_core(): void {
		$this->assertTrue( can_load_plugin() );
	}
}
