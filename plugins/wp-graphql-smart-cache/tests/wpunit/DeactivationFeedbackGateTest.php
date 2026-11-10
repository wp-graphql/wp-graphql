<?php
/**
 * Deactivation feedback is gated by this plugin's own Appsero opt-in.
 *
 * @package Wp_Graphql_Smart_Cache
 */

namespace WPGraphQL\SmartCache;

/**
 * @see https://github.com/wp-graphql/wp-graphql/issues/4510
 */
class DeactivationFeedbackGateTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * Must not call WPGraphQL\Admin\UsageTracking::gate_deactivation_feedback() — that method is
	 * missing on released WPGraphQL (class_exists is true, method is not), which fatals the site.
	 */
	public function testDeactivationFeedbackIsGatedByLocalAppseroOptIn(): void {
		$plugin_file = WPGRAPHQL_SMART_CACHE_PLUGIN_DIR . 'wp-graphql-smart-cache.php';
		$client      = new \Appsero\Client( 'smart-cache-deactivation-gate-test', 'Smart Cache Gate Test', $plugin_file );
		$insights    = $client->insights();
		$insights->init();

		wpgraphql_smart_cache_gate_deactivation_feedback( $insights, $client->slug );

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
		$slug      = ( new \Appsero\Client( 'deactivation-gate-test', 'WPGraphQL Smart Cache', WPGRAPHQL_SMART_CACHE_PLUGIN_DIR . 'wp-graphql-smart-cache.php' ) )->slug;
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
}
