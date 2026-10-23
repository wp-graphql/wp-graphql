<?php
/**
 * Tests for the bundled Appsero telemetry client.
 *
 * @package WPGraphQL\Acf\Tests\WPUnit
 */

class TelemetryTest extends \Tests\WPGraphQL\Acf\WPUnit\WPGraphQLAcfTestCase {

	/**
	 * Returns the version of a Composer package locked in a plugin's composer.lock.
	 *
	 * The lock file, not the vendor directory, is what releases are built from, and a local
	 * vendor directory can be out of date.
	 *
	 * @param string $plugin_dir The plugin's root directory, with a trailing slash.
	 * @param string $package    The Composer package name.
	 */
	private function get_locked_package_version( string $plugin_dir, string $package ): ?string {
		$lock_file = $plugin_dir . 'composer.lock';

		if ( ! file_exists( $lock_file ) ) {
			return null;
		}

		$lock = json_decode( (string) file_get_contents( $lock_file ), true );

		foreach ( $lock['packages'] ?? [] as $locked_package ) {
			if ( isset( $locked_package['name'], $locked_package['version'] ) && $package === $locked_package['name'] ) {
				return $locked_package['version'];
			}
		}

		return null;
	}

	/**
	 * Regression test for wp-graphql/wp-graphql#3934.
	 *
	 * Every WPGraphQL plugin bundles its own copy of the Appsero client, and whichever plugin
	 * loads first supplies the `Appsero\Client` class for all of them. An older copy here
	 * (1.4.0) translated plugin headers while the plugin file was loading, which WordPress 6.7+
	 * reports as loading translations too early, for every WPGraphQL plugin on the site. With
	 * errors displayed, that notice was printed into REST responses and broke them.
	 */
	public function testBundledAppseroClientMatchesWPGraphQL(): void {
		$acf_version  = $this->get_locked_package_version( WPGRAPHQL_FOR_ACF_VERSION_PLUGIN_DIR, 'appsero/client' );
		$core_version = $this->get_locked_package_version( WPGRAPHQL_PLUGIN_DIR, 'appsero/client' );

		if ( null === $core_version ) {
			$this->markTestSkipped( 'WPGraphQL is not loaded from a directory that includes its composer.lock.' );
		}

		$this->assertNotNull( $acf_version, 'WPGraphQL for ACF should lock a version of the Appsero client.' );
		$this->assertSame(
			$core_version,
			$acf_version,
			'WPGraphQL for ACF should lock the same Appsero client version as WPGraphQL, because the first copy loaded is used by every plugin.'
		);
	}
}
