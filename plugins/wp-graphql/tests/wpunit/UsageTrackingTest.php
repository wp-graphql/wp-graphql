<?php

use WPGraphQL\Admin\UsageTracking;

/**
 * Tests the usage tracking opt-out toggle.
 */
class UsageTrackingTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	private const SECTION = 'graphql_usage_tracking_test';

	/**
	 * @var string
	 */
	private $slug;

	/**
	 * @var \WPGraphQL\Admin\UsageTracking
	 */
	private $tracker;

	/**
	 * URLs of the outgoing HTTP requests made during the test.
	 *
	 * @var string[]
	 */
	private $requests = [];

	/**
	 * Bodies of the outgoing HTTP requests made during the test, keyed like $requests.
	 *
	 * @var array<int,mixed>
	 */
	private $request_bodies = [];

	/**
	 * Headers of the outgoing HTTP requests made during the test, keyed like $requests.
	 *
	 * @var array<int,mixed>
	 */
	private $request_headers = [];

	public function setUp(): void {
		parent::setUp();

		// The plugin's own Appsero slug, with a settings section of its own so the real toggle is left alone.
		$client     = new \Appsero\Client( 'usage-tracking-test-hash', 'Usage Tracking Test', WPGRAPHQL_PLUGIN_FILE );
		$this->slug = $client->slug;

		$this->tracker = new UsageTracking( $client->insights(), $this->slug, self::SECTION );
		$this->tracker->init();

		$this->requests = [];
		add_filter( 'pre_http_request', [ $this, 'record_request' ], 1, 3 );

		// Opting in requires the capability Appsero's own notice requires.
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		delete_option( self::SECTION );
		delete_option( $this->slug . '_allow_tracking' );
		wp_clear_scheduled_hook( $this->slug . '_tracker_send_event' );
	}

	public function tearDown(): void {
		remove_filter( 'pre_http_request', [ $this, 'record_request' ], 1 );
		$this->unhook_tracker();

		delete_option( self::SECTION );
		delete_option( $this->slug . '_allow_tracking' );
		wp_clear_scheduled_hook( $this->slug . '_tracker_send_event' );

		parent::tearDown();
	}

	/**
	 * Records the request and short-circuits it, so no test talks to the network.
	 *
	 * @param false|array<string,mixed>|\WP_Error $preempt Whether to preempt the request.
	 * @param array<string,mixed>                 $args    The request arguments.
	 * @param string                              $url     The request URL.
	 *
	 * @return array<string,mixed>
	 */
	public function record_request( $preempt, $args, $url ) {
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

	private function unhook_tracker(): void {
		remove_filter( 'pre_update_option_' . self::SECTION, [ $this->tracker, 'on_pre_update_option' ] );
		remove_action( 'add_option_' . $this->slug . '_allow_tracking', [ $this->tracker, 'sync_stored_value' ] );
		remove_action( 'update_option_' . $this->slug . '_allow_tracking', [ $this->tracker, 'sync_stored_value' ] );
		remove_action( 'delete_option_' . $this->slug . '_allow_tracking', [ $this->tracker, 'sync_stored_value' ] );
		remove_action( 'graphql_register_settings', [ $this->tracker, 'register_field' ] );
	}

	private function opt_in_via_notice(): void {
		update_option( $this->slug . '_allow_tracking', 'yes' );
		wp_schedule_event( time(), 'weekly', $this->slug . '_tracker_send_event' );
	}

	/**
	 * Changes the opt-in state while the toggle isn't listening, as when the plugin is inactive.
	 */
	private function set_allow_tracking_unsynced( string $value ): void {
		remove_action( 'update_option_' . $this->slug . '_allow_tracking', [ $this->tracker, 'sync_stored_value' ] );
		update_option( $this->slug . '_allow_tracking', $value );
		add_action( 'update_option_' . $this->slug . '_allow_tracking', [ $this->tracker, 'sync_stored_value' ] );
	}

	private function is_scheduled(): bool {
		return false !== wp_next_scheduled( $this->slug . '_tracker_send_event' );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function registered_field(): array {
		$this->tracker->register_field();

		$registry = new \WPGraphQL\Admin\Settings\SettingsRegistry();
		do_action( 'graphql_init_settings', $registry );

		foreach ( $registry->get_settings_fields()[ self::SECTION ] ?? [] as $field ) {
			if ( UsageTracking::FIELD_NAME === $field['name'] ) {
				return $field;
			}
		}

		$this->fail( 'The usage tracking field was not registered.' );
	}

	public function testToggleShowsOptedInForSitesThatOptedInThroughTheNotice(): void {
		$this->opt_in_via_notice();

		$field = $this->registered_field();

		$this->assertSame( 'checkbox', $field['type'] );
		$this->assertSame( 'on', $field['value'] );
	}

	public function testToggleShowsOptedOutByDefault(): void {
		$this->assertSame( 'off', $this->registered_field()['value'] );
	}

	public function testUncheckingTheToggleOptsOutAndClearsTheSchedule(): void {
		$this->opt_in_via_notice();
		$opted_out = did_action( $this->slug . '_tracker_optout' );

		// The settings form posts every field in the section, the toggle included.
		update_option( self::SECTION, [ UsageTracking::FIELD_NAME => 'off' ] );

		$this->assertFalse( $this->tracker->is_tracking_allowed() );
		$this->assertSame( 'no', get_option( $this->slug . '_allow_tracking' ) );
		$this->assertFalse( $this->is_scheduled() );
		$this->assertSame( $opted_out + 1, did_action( $this->slug . '_tracker_optout' ) );
	}

	public function testCheckingTheToggleOptsInAndSchedulesCheckIns(): void {
		$opted_in = did_action( $this->slug . '_tracker_optin' );

		update_option( self::SECTION, [ UsageTracking::FIELD_NAME => 'on' ] );

		$this->assertTrue( $this->tracker->is_tracking_allowed() );
		$this->assertTrue( $this->is_scheduled() );
		$this->assertSame( $opted_in + 1, did_action( $this->slug . '_tracker_optin' ) );
	}

	public function testSavingTheCurrentStateDoesNothing(): void {
		$this->opt_in_via_notice();
		$opted_in = did_action( $this->slug . '_tracker_optin' );

		update_option( self::SECTION, [ UsageTracking::FIELD_NAME => 'on' ] );

		$this->assertTrue( $this->tracker->is_tracking_allowed() );
		$this->assertSame( $opted_in, did_action( $this->slug . '_tracker_optin' ) );
	}

	public function testOptingOutThroughTheNoticeUpdatesTheStoredToggle(): void {
		update_option( self::SECTION, [ UsageTracking::FIELD_NAME => 'on' ] );

		// The notice's "No thanks" and the opt-out URL both end in Insights::optout().
		( new \Appsero\Client( 'usage-tracking-test-hash', 'Usage Tracking Test', WPGRAPHQL_PLUGIN_FILE ) )->insights()->optout();

		$this->assertSame( 'off', get_option( self::SECTION )[ UsageTracking::FIELD_NAME ] );
	}

	public function testWritingTheOptionDirectlyUpdatesTheStoredToggle(): void {
		update_option( self::SECTION, [ UsageTracking::FIELD_NAME => 'off' ] );

		// The first opt-in on a site adds the option, as WP-CLI or another plugin might.
		add_option( $this->slug . '_allow_tracking', 'yes' );
		$this->assertSame( 'on', get_option( self::SECTION )[ UsageTracking::FIELD_NAME ] );

		update_option( $this->slug . '_allow_tracking', 'no' );
		$this->assertSame( 'off', get_option( self::SECTION )[ UsageTracking::FIELD_NAME ] );

		update_option( $this->slug . '_allow_tracking', 'yes' );
		delete_option( $this->slug . '_allow_tracking' );
		$this->assertSame( 'off', get_option( self::SECTION )[ UsageTracking::FIELD_NAME ] );
	}

	public function testADirectWriteDoesNotLetAnUnrelatedSaveOptOut(): void {
		update_option( self::SECTION, [ UsageTracking::FIELD_NAME => 'off' ] );
		update_option( $this->slug . '_allow_tracking', 'yes' );

		// Saving another field re-saves the stored copy of the toggle along with it.
		$stored                     = get_option( self::SECTION );
		$stored['some_other_field'] = 'changed';
		update_option( self::SECTION, $stored );

		$this->assertTrue( $this->tracker->is_tracking_allowed() );
	}

	public function testSavingTheToggleWritesTheSectionOnce(): void {
		update_option(
			self::SECTION,
			[
				UsageTracking::FIELD_NAME => 'off',
				'some_other_field'        => 'old',
			]
		);

		$writes = [];
		$record = static function ( $option, $old_value, $value ) use ( &$writes ): void {
			if ( self::SECTION === $option ) {
				$writes[] = $value;
			}
		};
		add_action( 'updated_option', $record, 10, 3 );

		// The settings form saves every field in the section at once.
		$saved = [
			UsageTracking::FIELD_NAME => 'on',
			'some_other_field'        => 'new',
		];
		update_option( self::SECTION, $saved );

		remove_action( 'updated_option', $record );

		$this->assertTrue( $this->tracker->is_tracking_allowed() );
		$this->assertSame( [ $saved ], $writes );
	}

	public function testUncheckingTheToggleWritesTheSectionOnce(): void {
		$this->opt_in_via_notice();
		update_option(
			self::SECTION,
			[
				UsageTracking::FIELD_NAME => 'on',
				'some_other_field'        => 'old',
			]
		);

		$writes = [];
		$record = static function ( $option, $old_value, $value ) use ( &$writes ): void {
			if ( self::SECTION === $option ) {
				$writes[] = $value;
			}
		};
		add_action( 'updated_option', $record, 10, 3 );

		// Opting out writes Appsero's option during this save, which must not save the section again.
		$saved = [
			UsageTracking::FIELD_NAME => 'off',
			'some_other_field'        => 'new',
		];
		update_option( self::SECTION, $saved );

		remove_action( 'updated_option', $record );

		$this->assertFalse( $this->tracker->is_tracking_allowed() );
		$this->assertSame( [ $saved ], $writes );
	}

	public function testOptingInRequiresManageOptions(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		update_option( self::SECTION, [ UsageTracking::FIELD_NAME => 'on' ] );

		$this->assertFalse( $this->tracker->is_tracking_allowed() );
		$this->assertFalse( $this->is_scheduled() );
		$this->assertSame( [], $this->requests );
	}

	public function testOptingInIsSkippedWithoutAUser(): void {
		wp_set_current_user( 0 );

		update_option( self::SECTION, [ UsageTracking::FIELD_NAME => 'on' ] );

		$this->assertFalse( $this->tracker->is_tracking_allowed() );
	}

	public function testOptingOutDoesNotRequireManageOptions(): void {
		$this->opt_in_via_notice();
		wp_set_current_user( 0 );

		update_option( self::SECTION, [ UsageTracking::FIELD_NAME => 'off' ] );

		$this->assertFalse( $this->tracker->is_tracking_allowed() );
	}

	public function testStaleStoredToggleNeverOptsIn(): void {
		// Stored "on", then opted out by a path that didn't update the stored copy.
		update_option( self::SECTION, [ UsageTracking::FIELD_NAME => 'on' ] );
		$this->set_allow_tracking_unsynced( 'no' );

		// Re-saving the section for a different field carries the stale value along.
		update_option(
			self::SECTION,
			[
				UsageTracking::FIELD_NAME => 'on',
				'some_other_field'        => 'changed',
			]
		);

		$this->assertFalse( $this->tracker->is_tracking_allowed() );
	}

	public function testUncheckingOptsOutWhenTheStoredValueIsAlreadyOff(): void {
		// Stored "off", then opted in by a path that didn't update the stored copy.
		update_option( self::SECTION, [ UsageTracking::FIELD_NAME => 'off' ] );
		$this->set_allow_tracking_unsynced( 'yes' );
		wp_schedule_event( time(), 'weekly', $this->slug . '_tracker_send_event' );

		// Saving the form posts the same value that's stored, so WordPress skips the update.
		update_option( self::SECTION, [ UsageTracking::FIELD_NAME => 'off' ] );

		$this->assertFalse( $this->tracker->is_tracking_allowed() );
		$this->assertFalse( $this->is_scheduled() );
	}

	public function testSavingWithoutTheToggleLeavesTrackingAlone(): void {
		$this->opt_in_via_notice();

		update_option( self::SECTION, [ 'some_other_field' => 'on' ] );

		$this->assertTrue( $this->tracker->is_tracking_allowed() );
		$this->assertTrue( $this->is_scheduled() );
	}

	public function testNoCheckInsAreSentAfterOptingOut(): void {
		$this->opt_in_via_notice();
		update_option( self::SECTION, [ UsageTracking::FIELD_NAME => 'off' ] );
		$this->requests = [];

		do_action( $this->slug . '_tracker_send_event' );

		$this->assertSame( [], $this->requests );
	}

	public function testTheOptOutRequestCarriesNoSiteData(): void {
		$this->opt_in_via_notice();
		$this->requests       = [];
		$this->request_bodies = [];

		update_option( self::SECTION, [ UsageTracking::FIELD_NAME => 'off' ] );

		$this->assertCount( 1, $this->requests );
		$this->assertStringEndsWith( '/tracking-skipped', $this->requests[0] );
		$this->assertEqualsCanonicalizing( [ 'hash', 'previously_skipped', 'client' ], array_keys( $this->request_bodies[0] ) );
	}

	public function testTheRealOptOutRequestIsMirroredWithAHashedSiteIdentifier(): void {
		// The plugin's real hash, which the mirror filter in wp-graphql.php matches on.
		$client = new \Appsero\Client( 'cd0d1172-95a0-4460-a36a-2c303807c9ef', 'WPGraphQL', WPGRAPHQL_PLUGIN_FILE );

		$this->unhook_tracker();
		$this->tracker = new UsageTracking( $client->insights(), $client->slug, self::SECTION );
		$this->tracker->init();

		$this->opt_in_via_notice();
		$this->requests        = [];
		$this->request_bodies  = [];
		$this->request_headers = [];

		update_option( self::SECTION, [ UsageTracking::FIELD_NAME => 'off' ] );

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

	public function testPluginRegistersTheToggleOnTheGeneralSettings(): void {
		$registry = new \WPGraphQL\Admin\Settings\SettingsRegistry();
		do_action( 'graphql_init_settings', $registry );

		$names = wp_list_pluck( $registry->get_settings_fields()['graphql_general_settings'] ?? [], 'name' );

		$this->assertContains( UsageTracking::FIELD_NAME, $names );
	}
}
