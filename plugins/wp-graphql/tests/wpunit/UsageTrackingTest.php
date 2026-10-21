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

	public function setUp(): void {
		parent::setUp();

		// The plugin's own Appsero slug, with a settings section of its own so the real toggle is left alone.
		$client     = new \Appsero\Client( 'usage-tracking-test-hash', 'Usage Tracking Test', WPGRAPHQL_PLUGIN_FILE );
		$this->slug = $client->slug;

		$this->tracker = new UsageTracking( $client->insights(), $this->slug, self::SECTION );
		$this->tracker->init();

		$this->requests = [];
		add_filter( 'pre_http_request', [ $this, 'record_request' ], 1, 3 );

		delete_option( self::SECTION );
		delete_option( $this->slug . '_allow_tracking' );
		wp_clear_scheduled_hook( $this->slug . '_tracker_send_event' );
	}

	public function tearDown(): void {
		remove_filter( 'pre_http_request', [ $this, 'record_request' ], 1 );
		remove_action( 'add_option_' . self::SECTION, [ $this->tracker, 'on_option_added' ] );
		remove_action( 'update_option_' . self::SECTION, [ $this->tracker, 'on_option_updated' ] );
		remove_action( $this->slug . '_tracker_optin', [ $this->tracker, 'sync_stored_value' ] );
		remove_action( $this->slug . '_tracker_optout', [ $this->tracker, 'sync_stored_value' ] );
		remove_action( 'graphql_register_settings', [ $this->tracker, 'register_field' ] );

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
		$this->requests[]       = $url;
		$this->request_bodies[] = $args['body'] ?? null;

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

	private function opt_in_via_notice(): void {
		update_option( $this->slug . '_allow_tracking', 'yes' );
		wp_schedule_event( time(), 'weekly', $this->slug . '_tracker_send_event' );
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

	public function testStaleStoredToggleNeverOptsIn(): void {
		// Stored "on", then opted out by a path that didn't update the stored copy.
		update_option( self::SECTION, [ UsageTracking::FIELD_NAME => 'on' ] );
		update_option( $this->slug . '_allow_tracking', 'no' );

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

	public function testPluginRegistersTheToggleOnTheGeneralSettings(): void {
		$registry = new \WPGraphQL\Admin\Settings\SettingsRegistry();
		do_action( 'graphql_init_settings', $registry );

		$names = wp_list_pluck( $registry->get_settings_fields()['graphql_general_settings'] ?? [], 'name' );

		$this->assertContains( UsageTracking::FIELD_NAME, $names );
	}
}
