<?php
/**
 * Tests the IDE's usage tracking opt-out toggle on the IDE Settings tab.
 *
 * @package WPGraphQLIDE
 */

class UsageTrackingSettingTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * The IDE's Appsero client slug.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * The URLs requested during the test.
	 *
	 * @var string[]
	 */
	private $requests = [];

	/**
	 * The body of each request, in the same order as $requests.
	 *
	 * @var array<int,mixed>
	 */
	private $request_bodies = [];

	/**
	 * The headers of each request, in the same order as $requests.
	 *
	 * @var array<int,array<string,string>>
	 */
	private $request_headers = [];

	public function setUp(): void {
		parent::setUp();

		$this->slug = ( new \Appsero\Client( 'usage-tracking-test-hash', 'WPGraphQL IDE', WPGRAPHQL_IDE_PLUGIN_FILE ) )->slug;

		add_filter( 'pre_http_request', [ $this, 'short_circuit_request' ], 1, 3 );

		delete_option( 'graphql_ide_settings' );
		delete_option( $this->slug . '_allow_tracking' );
		wp_clear_scheduled_hook( $this->slug . '_tracker_send_event' );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->clearSchema();
	}

	public function tearDown(): void {
		remove_filter( 'pre_http_request', [ $this, 'short_circuit_request' ], 1 );

		delete_option( 'graphql_ide_settings' );
		delete_option( $this->slug . '_allow_tracking' );
		wp_clear_scheduled_hook( $this->slug . '_tracker_send_event' );

		$this->clearSchema();
		parent::tearDown();
	}

	/**
	 * Records each request and keeps it from going over the network.
	 *
	 * The IDE's mirror filter runs after this one, so a mirrored request is recorded too.
	 *
	 * @param false|array<string,mixed>|\WP_Error $preempt Whether to preempt the request.
	 * @param array<string,mixed>                $args    The request arguments.
	 * @param string                             $url     The request URL.
	 *
	 * @return array<string,mixed>
	 */
	public function short_circuit_request( $preempt, $args, $url ): array {
		$this->requests[]        = $url;
		$this->request_bodies[]  = $args['body'] ?? null;
		$this->request_headers[] = $args['headers'] ?? [];

		return [
			'headers'  => [],
			'body'     => '',
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'cookies'  => [],
		];
	}

	/**
	 * Builds the settings registry the way the settings screens do, which also captures the IDE's snapshot.
	 *
	 * @return array<string,mixed>|null The IDE's usage tracking field, if registered.
	 */
	private function usage_tracking_field(): ?array {
		$registry = new \WPGraphQL\Admin\Settings\SettingsRegistry();
		do_action( 'graphql_init_settings', $registry );

		foreach ( $registry->get_settings_fields()['graphql_ide_settings'] ?? [] as $field ) {
			if ( 'share_usage_data' === $field['name'] ) {
				return $field;
			}
		}

		return null;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function set_toggle( bool $checked ): array {
		return $this->graphql(
			[
				'query'     => '
					mutation ( $input: UpdateGraphqlSettingInput! ) {
						updateGraphqlSetting( input: $input ) {
							success
						}
					}
				',
				'variables' => [
					'input' => [
						'section' => 'graphql_ide_settings',
						'field'   => 'share_usage_data',
						'value'   => [ 'checkbox' => $checked ],
					],
				],
			]
		);
	}

	public function test_toggle_is_registered_on_the_ide_settings_tab(): void {
		$field = $this->usage_tracking_field();

		$this->assertNotNull( $field );
		$this->assertSame( 'checkbox', $field['type'] );
		$this->assertSame( 'off', $field['value'] );
	}

	public function test_unchecking_the_toggle_in_the_ide_opts_out(): void {
		// Opted in through the admin notice before the toggle existed.
		update_option( $this->slug . '_allow_tracking', 'yes' );
		wp_schedule_event( time(), 'weekly', $this->slug . '_tracker_send_event' );
		$this->usage_tracking_field();

		$response = $this->set_toggle( false );

		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertTrue( $response['data']['updateGraphqlSetting']['success'] );
		$this->assertSame( 'no', get_option( $this->slug . '_allow_tracking' ) );
		$this->assertFalse( wp_next_scheduled( $this->slug . '_tracker_send_event' ) );
	}

	public function test_checking_the_toggle_in_the_ide_opts_in(): void {
		$this->usage_tracking_field();

		$response = $this->set_toggle( true );

		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertTrue( $response['data']['updateGraphqlSetting']['success'] );
		$this->assertSame( 'yes', get_option( $this->slug . '_allow_tracking' ) );
		$this->assertNotFalse( wp_next_scheduled( $this->slug . '_tracker_send_event' ) );
	}

	public function test_the_opt_out_request_is_mirrored_with_a_hashed_site_identifier(): void {
		update_option( $this->slug . '_allow_tracking', 'yes' );
		$this->usage_tracking_field();
		$this->requests        = [];
		$this->request_bodies  = [];
		$this->request_headers = [];

		$this->set_toggle( false );

		// The IDE's real Appsero client sends one request, and its mirror sends the copy.
		$this->assertSame(
			[
				'https://api.appsero.com/tracking-skipped',
				'https://telemetry.wpgraphql.com/api/appsero/tracking-skipped',
			],
			$this->requests
		);

		foreach ( array_keys( $this->requests ) as $i ) {
			$this->assertEqualsCanonicalizing( [ 'hash', 'previously_skipped', 'client' ], array_keys( $this->request_bodies[ $i ] ) );
			$this->assertSame( 'Appsero/' . md5( esc_url( home_url() ) ) . ';', $this->request_headers[ $i ]['user-agent'] );
		}
	}

	public function test_lowering_the_settings_capability_does_not_let_editors_opt_in(): void {
		$lower_cap = static function (): string {
			return 'edit_others_posts';
		};
		add_filter( 'graphql_manage_settings_cap', $lower_cap );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$this->usage_tracking_field();

		$response = $this->set_toggle( true );

		remove_filter( 'graphql_manage_settings_cap', $lower_cap );

		// The opt-in is held back, and the stored toggle records that rather than the request.
		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertSame( 'off', get_option( 'graphql_ide_settings' )['share_usage_data'] ?? 'off' );

		$this->assertNotSame( 'yes', get_option( $this->slug . '_allow_tracking' ) );
		$this->assertFalse( wp_next_scheduled( $this->slug . '_tracker_send_event' ) );
	}

	/**
	 * Local gate for when WPGraphQL's UsageTracking lacks gate_deactivation_feedback().
	 *
	 * @see https://github.com/wp-graphql/wp-graphql/issues/4510
	 */
	public function test_deactivation_feedback_is_gated_by_local_appsero_opt_in(): void {
		$client   = new \Appsero\Client( 'ide-deactivation-gate-test', 'IDE Gate Test', WPGRAPHQL_IDE_PLUGIN_FILE );
		$insights = $client->insights();
		$insights->init();

		\WPGraphQLIDE\Telemetry::gate_deactivation_feedback( $insights, $client->slug );

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
	public function test_plugins_own_deactivation_form_is_gated(): void {
		$slug      = ( new \Appsero\Client( 'deactivation-gate-test', 'WPGraphQL IDE', WPGRAPHQL_IDE_PLUGIN_FILE ) )->slug;
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
		$client = new \Appsero\Client( 'e90103d6-2c09-4152-96e0-eb7d0d3b5c74', 'WPGraphQL IDE', WPGRAPHQL_IDE_PLUGIN_FILE );
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
