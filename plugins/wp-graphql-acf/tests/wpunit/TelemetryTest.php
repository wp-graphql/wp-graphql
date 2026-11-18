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

	/**
	 * Deactivation feedback must be gated by this plugin's own Appsero opt-in, without calling
	 * WPGraphQL\Admin\UsageTracking::gate_deactivation_feedback() (missing on older WPGraphQL).
	 *
	 * @see https://github.com/wp-graphql/wp-graphql/issues/4510
	 */
	public function testDeactivationFeedbackIsGatedByLocalAppseroOptIn(): void {
		$client   = new \Appsero\Client( 'acf-deactivation-gate-test', 'ACF Gate Test', WPGRAPHQL_FOR_ACF_VERSION_PLUGIN_DIR . 'wpgraphql-acf.php' );
		$insights = $client->insights();
		$insights->init();

		graphql_acf_gate_deactivation_feedback( $insights, $client->slug );

		$this->assertFalse(
			has_action( 'admin_footer', [ $insights, 'deactivate_scripts' ] ),
			'Appsero should no longer render the form unconditionally.'
		);
		$this->assertNotFalse(
			has_action( 'admin_footer' ),
			'A gated admin_footer callback should still be registered.'
		);

		$ajax_hook = 'wp_ajax_' . $client->slug . '_submit-uninstall-reason';
		$this->assertFalse( has_action( $ajax_hook, [ $insights, 'uninstall_reason_submission' ] ) );
		$this->assertNotFalse( has_action( $ajax_hook ) );

		delete_option( $client->slug . '_allow_tracking' );

		global $pagenow;
		$pagenow = 'plugins.php';
		ob_start();
		do_action( 'admin_footer' );
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'Goodbyes are always hard', (string) $output );
	}

	/**
	 * The plugin's own Appsero client, set up as the plugin loads, has its deactivation form gated.
	 *
	 * @see https://github.com/wp-graphql/wp-graphql/issues/4510
	 */
	public function testPluginsOwnDeactivationFormIsGated(): void {
		$slug      = ( new \Appsero\Client( 'deactivation-gate-test', 'WPGraphQL for Advanced Custom Fields', WPGRAPHQL_FOR_ACF_VERSION_PLUGIN_DIR . 'wpgraphql-acf.php' ) )->slug;
		$ajax_hook = 'wp_ajax_' . $slug . '_submit-uninstall-reason';

		$this->assertNotFalse( has_action( $ajax_hook ), 'The plugin should set up its Appsero client as it loads.' );
		$this->assertSame( [], $this->ungated_deactivation_hooks( $slug ), 'Appsero\'s own deactivation form hooks should be replaced by the gated ones.' );
	}

	/**
	 * Lists the hooks where this plugin's own Appsero client still renders or accepts the
	 * deactivation feedback form without checking the opt-in.
	 *
	 * @param string $slug The plugin's Appsero client slug.
	 *
	 * @return string[]
	 */
	private function ungated_deactivation_hooks( string $slug ): array {
		global $wp_filter;

		$property = new \ReflectionProperty( \Appsero\Insights::class, 'client' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}

		$hooks = [
			'admin_footer'                                  => 'deactivate_scripts',
			'wp_ajax_' . $slug . '_submit-uninstall-reason' => 'uninstall_reason_submission',
		];

		$ungated = [];
		foreach ( $hooks as $hook => $method ) {
			if ( empty( $wp_filter[ $hook ] ) ) {
				continue;
			}

			foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
				foreach ( $callbacks as $callback ) {
					$function = $callback['function'];

					if ( is_array( $function ) && $function[0] instanceof \Appsero\Insights && $method === $function[1] && $slug === $property->getValue( $function[0] )->slug ) {
						$ungated[] = $hook;
					}
				}
			}
		}

		return $ungated;
	}

	/**
	 * The copy sent to telemetry.wpgraphql.com leaves out the site's admin email address and an
	 * administrator's name, and Appsero's own request is sent unchanged.
	 */
	public function testTheTelemetryCopyLeavesOutTheAdminEmailAndName(): void {
		$sent    = [];
		$capture = static function ( $preempt, $args, $url ) use ( &$sent ) {
			if ( false !== strpos( $url, 'api.appsero.com' ) || false !== strpos( $url, 'telemetry.wpgraphql.com' ) ) {
				$sent[ $url ] = $args['body'] ?? null;
			}

			return [
				'headers'  => [],
				'body'     => '',
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
			];
		};
		add_filter( 'pre_http_request', $capture, 0, 3 );

		// The plugin's own hash, so its mirror sends a copy of the check-in.
		$client = new \Appsero\Client( '4988d797-77ee-4201-84ce-1d610379f843', 'WPGraphQL for Advanced Custom Fields', WPGRAPHQL_FOR_ACF_VERSION_PLUGIN_DIR . 'wpgraphql-acf.php' );
		$client->insights()->send_tracking_data( true );

		remove_filter( 'pre_http_request', $capture, 0 );

		$this->assertSame(
			[
				'https://api.appsero.com/track',
				'https://telemetry.wpgraphql.com/api/appsero/track',
			],
			array_keys( $sent )
		);

		$appsero = $sent['https://api.appsero.com/track'];
		$copy    = $sent['https://telemetry.wpgraphql.com/api/appsero/track'];

		$this->assertIsArray( $appsero );
		$this->assertSame( get_option( 'admin_email' ), $appsero['admin_email'] );
		$this->assertArrayHasKey( 'first_name', $appsero );
		$this->assertArrayHasKey( 'last_name', $appsero );

		// Everything else in the copy is the same as what Appsero received.
		unset( $appsero['admin_email'], $appsero['first_name'], $appsero['last_name'] );
		$this->assertSame( $appsero, $copy );
	}
}
