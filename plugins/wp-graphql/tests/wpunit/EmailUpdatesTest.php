<?php

use WPGraphQL\Admin\EmailUpdates;
use WPGraphQL\Admin\UsageTracking;

/**
 * Tests the email updates opt-in, which is separate from the usage tracking opt-in.
 */
class EmailUpdatesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	private const SECTION = 'graphql_email_updates_test';

	private const HASH = 'email-updates-test-hash';

	private const SLUG = 'email-updates-test';

	private const VERSION = '9.8.7';

	/**
	 * @var \WPGraphQL\Admin\EmailUpdates
	 */
	private $email_updates;

	/**
	 * The outgoing HTTP requests made during the test, as `url` and `args`.
	 *
	 * @var array<int,array{url:string,args:array<string,mixed>}>
	 */
	private $requests = [];

	/**
	 * @var \WP_User
	 */
	private $admin;

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'add_settings_error' ) ) {
			require_once ABSPATH . 'wp-admin/includes/template.php';
		}

		$this->email_updates = new EmailUpdates( self::HASH, self::SLUG, self::VERSION, self::SECTION );
		$this->email_updates->init();

		$this->requests = [];
		add_filter( 'pre_http_request', [ $this, 'record_request' ], 1, 3 );

		// The site's admin email setting is a different address from the person saving the settings.
		update_option( 'admin_email', 'site-owner@example.com' );

		$this->admin = self::factory()->user->create_and_get(
			[
				'role'       => 'administrator',
				'user_email' => 'saving-admin@example.com',
			]
		);
		wp_set_current_user( $this->admin->ID );

		$GLOBALS['wp_settings_errors'] = [];

		delete_option( self::SECTION );
		delete_option( self::SLUG . '_email_updates' );
	}

	public function tearDown(): void {
		remove_filter( 'pre_http_request', [ $this, 'record_request' ], 1 );
		remove_filter( 'pre_update_option_' . self::SECTION, [ $this->email_updates, 'on_pre_update_option' ] );
		remove_action( 'graphql_register_settings', [ $this->email_updates, 'register_fields' ] );
		remove_action( 'graphql_init_settings', [ $this->email_updates, 'add_fields' ] );
		remove_action( 'graphql_delete_data', [ $this->email_updates, 'delete_request' ] );

		delete_option( self::SECTION );
		delete_option( self::SLUG . '_email_updates' );
		$GLOBALS['wp_settings_errors'] = [];

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
		$this->requests[] = [
			'url'  => $url,
			'args' => $args,
		];

		return [
			'headers'  => [],
			'body'     => '',
			'response' => [
				'code'    => 202,
				'message' => 'Accepted',
			],
			'cookies'  => [],
		];
	}

	/**
	 * @return array<int,array{url:string,args:array<string,mixed>}>
	 */
	private function subscribe_requests(): array {
		return array_values(
			array_filter(
				$this->requests,
				static function ( $request ) {
					return EmailUpdates::ENDPOINT === $request['url'];
				}
			)
		);
	}

	/**
	 * Saves the section the way the settings form posts it.
	 */
	private function save( string $checkbox, ?string $address = null ): void {
		update_option(
			self::SECTION,
			[
				EmailUpdates::FIELD_NAME         => $checkbox,
				EmailUpdates::ADDRESS_FIELD_NAME => $address ?? $this->admin->user_email,
			]
		);
	}

	/**
	 * @return array<string,array<string,mixed>> The registered fields, keyed by name.
	 */
	private function registered_fields(): array {
		$this->email_updates->register_fields();

		$registry = new \WPGraphQL\Admin\Settings\SettingsRegistry();
		do_action( 'graphql_init_settings', $registry );

		$fields = [];
		foreach ( $registry->get_settings_fields()[ self::SECTION ] ?? [] as $field ) {
			$fields[ $field['name'] ] = $field;
		}

		return $fields;
	}

	/**
	 * @return string[] The codes of the settings errors added during the test.
	 */
	private function settings_error_codes(): array {
		return wp_list_pluck( get_settings_errors( self::SECTION ), 'code' );
	}

	public function testTheCheckboxIsOffByDefault(): void {
		$fields = $this->registered_fields();

		$this->assertSame( 'checkbox', $fields[ EmailUpdates::FIELD_NAME ]['type'] );
		$this->assertSame( 'off', $fields[ EmailUpdates::FIELD_NAME ]['default'] );
		$this->assertSame( 'off', $fields[ EmailUpdates::FIELD_NAME ]['value'] );
		$this->assertNull( $this->email_updates->get_request() );
	}

	public function testTheAddressIsPrefilledWithTheCurrentUsersEmailNotTheAdminEmail(): void {
		$fields = $this->registered_fields();

		$this->assertSame( 'saving-admin@example.com', $fields[ EmailUpdates::ADDRESS_FIELD_NAME ]['value'] );
		$this->assertNotSame( get_option( 'admin_email' ), $fields[ EmailUpdates::ADDRESS_FIELD_NAME ]['value'] );
	}

	public function testTheAddressIsPrefilledForTheUserViewingTheSettingsNotTheOneWhoLoadedThePlugin(): void {
		// The fields are registered early in the request, before the settings page is built.
		$this->email_updates->register_fields();

		$other_admin = self::factory()->user->create( [ 'role' => 'administrator', 'user_email' => 'other-admin@example.com' ] );
		wp_set_current_user( $other_admin );

		$this->assertSame( 'other-admin@example.com', $this->registered_fields()[ EmailUpdates::ADDRESS_FIELD_NAME ]['value'] );
	}

	public function testTheAddressFieldOnlyShowsWhileTheCheckboxIsChecked(): void {
		$fields = $this->registered_fields();

		$this->assertSame( EmailUpdates::FIELD_NAME, $fields[ EmailUpdates::ADDRESS_FIELD_NAME ]['depends_on'] );
	}

	public function testSavingWithItOffSendsNothing(): void {
		$this->save( 'off' );

		$this->assertSame( [], $this->requests );
		$this->assertNull( $this->email_updates->get_request() );
		$this->assertSame( 'off', get_option( self::SECTION )[ EmailUpdates::FIELD_NAME ] );

		// The prefilled address isn't kept, since no confirmation was requested for it.
		$this->assertArrayNotHasKey( EmailUpdates::ADDRESS_FIELD_NAME, get_option( self::SECTION ) );
	}

	public function testTurningItOnLaterUsesTheAddressOfWhoeverTurnsItOn(): void {
		// One admin saves the form without email updates, posting their prefilled address.
		$this->save( 'off' );

		// Another admin turns it on through a write that only sets the checkbox.
		$other_admin = self::factory()->user->create( [ 'role' => 'administrator', 'user_email' => 'other-admin@example.com' ] );
		wp_set_current_user( $other_admin );
		update_option( self::SECTION, array_merge( get_option( self::SECTION ), [ EmailUpdates::FIELD_NAME => 'on' ] ) );

		$this->assertCount( 1, $this->subscribe_requests() );
		$this->assertSame( 'other-admin@example.com', $this->requests[0]['args']['body']['email'] );
	}

	public function testTurningItOnSendsOneNonBlockingSubscribeRequest(): void {
		$this->save( 'on' );

		$this->assertCount( 1, $this->requests );
		$this->assertSame( EmailUpdates::ENDPOINT, $this->requests[0]['url'] );
		$this->assertSame( 'https://telemetry.wpgraphql.com/api/v1/email/subscribe', $this->requests[0]['url'] );

		$args = $this->requests[0]['args'];
		$this->assertSame( 'POST', $args['method'] );
		$this->assertFalse( $args['blocking'] );
		$this->assertSame(
			[
				'hash'           => self::HASH,
				'email'          => 'saving-admin@example.com',
				'site_url'       => home_url(),
				'plugin_version' => self::VERSION,
			],
			$args['body']
		);
	}

	public function testTurningItOnRecordsTheRequestAndKeepsTheBoxChecked(): void {
		$before = time();
		$this->save( 'on' );

		$request = $this->email_updates->get_request();
		$this->assertNotNull( $request );
		$this->assertSame( 'saving-admin@example.com', $request['email'] );
		$this->assertGreaterThanOrEqual( $before, $request['requested_at'] );

		$stored = get_option( self::SECTION );
		$this->assertSame( 'on', $stored[ EmailUpdates::FIELD_NAME ] );
		$this->assertSame( 'saving-admin@example.com', $stored[ EmailUpdates::ADDRESS_FIELD_NAME ] );
		$this->assertContains( 'email_updates_requested', $this->settings_error_codes() );
	}

	public function testTheCheckboxDescriptionShowsWhenAndForWhomAConfirmationWasRequested(): void {
		$this->save( 'on' );

		$fields   = $this->registered_fields();
		$checkbox = $fields[ EmailUpdates::FIELD_NAME ];
		$request  = $this->email_updates->get_request();

		$this->assertSame( 'on', $checkbox['value'] );
		$this->assertStringContainsString( 'saving-admin@example.com', $checkbox['desc'] );
		$this->assertStringContainsString( wp_date( get_option( 'date_format' ), $request['requested_at'] ), $checkbox['desc'] );
		$this->assertSame( 'saving-admin@example.com', $fields[ EmailUpdates::ADDRESS_FIELD_NAME ]['value'] );
	}

	public function testTheAddressCanBeEdited(): void {
		$this->save( 'on', 'updates-inbox@example.com' );

		$this->assertCount( 1, $this->subscribe_requests() );
		$this->assertSame( 'updates-inbox@example.com', $this->requests[0]['args']['body']['email'] );
	}

	public function testResavingUnchangedSendsNothing(): void {
		$this->save( 'on' );
		$this->requests = [];

		$this->save( 'on' );

		// Saving a different field of the same section carries the email fields along unchanged.
		update_option(
			self::SECTION,
			array_merge( get_option( self::SECTION ), [ 'some_other_field' => 'changed' ] )
		);

		$this->assertSame( [], $this->requests );
	}

	public function testAnAddressThatOnlyDiffersInCaseIsUnchanged(): void {
		$this->save( 'on' );
		$this->requests = [];

		$this->save( 'on', 'Saving-Admin@Example.com' );

		$this->assertSame( [], $this->requests );
	}

	public function testChangingTheAddressWhileOnSendsOneRequest(): void {
		$this->save( 'on' );
		$this->requests = [];

		$this->save( 'on', 'new-inbox@example.com' );

		$this->assertCount( 1, $this->requests );
		$this->assertSame( EmailUpdates::ENDPOINT, $this->requests[0]['url'] );
		$this->assertSame( 'new-inbox@example.com', $this->requests[0]['args']['body']['email'] );
		$this->assertSame( 'new-inbox@example.com', $this->email_updates->get_request()['email'] );
	}

	public function testChangingTheAddressWhileOffSendsNothing(): void {
		$this->save( 'off', 'new-inbox@example.com' );

		$this->assertSame( [], $this->requests );
	}

	public function testAnInvalidEmailSendsNothingAndAddsASettingsError(): void {
		$this->save( 'on', 'not-an-email' );

		$this->assertSame( [], $this->requests );
		$this->assertNull( $this->email_updates->get_request() );
		$this->assertContains( 'email_updates_invalid_email', $this->settings_error_codes() );
		$this->assertSame( 'off', get_option( self::SECTION )[ EmailUpdates::FIELD_NAME ] );
	}

	public function testAnEmptyEmailSendsNothingAndAddsASettingsError(): void {
		$this->save( 'on', '' );

		$this->assertSame( [], $this->requests );
		$this->assertContains( 'email_updates_invalid_email', $this->settings_error_codes() );
	}

	public function testAnInvalidNewAddressKeepsTheEarlierRequest(): void {
		$this->save( 'on' );
		$this->requests = [];

		$this->save( 'on', 'not-an-email' );

		$this->assertSame( [], $this->requests );
		$this->assertSame( 'saving-admin@example.com', $this->email_updates->get_request()['email'] );
		$this->assertSame( 'saving-admin@example.com', get_option( self::SECTION )[ EmailUpdates::ADDRESS_FIELD_NAME ] );
	}

	public function testAWriteWithoutTheAddressUsesTheCurrentUsersEmail(): void {
		// A write that sets only the checkbox, like a settings mutation that updates one field at a time.
		update_option( self::SECTION, [ EmailUpdates::FIELD_NAME => 'on' ] );

		$this->assertCount( 1, $this->subscribe_requests() );
		$this->assertSame( 'saving-admin@example.com', $this->requests[0]['args']['body']['email'] );
	}

	public function testAUserWithoutManageOptionsSendsNothing(): void {
		// The settings capability is filterable, so someone without manage_options can reach the form.
		$editor = self::factory()->user->create( [ 'role' => 'editor' ] );
		wp_set_current_user( $editor );

		$this->save( 'on', 'editor-inbox@example.com' );

		$this->assertSame( [], $this->requests );
		$this->assertNull( $this->email_updates->get_request() );
		$this->assertContains( 'email_updates_not_allowed', $this->settings_error_codes() );
		$this->assertSame( 'off', get_option( self::SECTION )[ EmailUpdates::FIELD_NAME ] );
	}

	public function testASaveWithoutAUserSendsNothing(): void {
		wp_set_current_user( 0 );

		$this->save( 'on', 'cli-inbox@example.com' );

		$this->assertSame( [], $this->requests );
	}

	public function testUncheckingSendsNothingAndClearsTheRequest(): void {
		$this->save( 'on' );
		$this->requests = [];

		$this->save( 'off' );

		$this->assertSame( [], $this->requests );
		$this->assertNull( $this->email_updates->get_request() );
		$this->assertSame( 'off', $this->registered_fields()[ EmailUpdates::FIELD_NAME ]['value'] );
	}

	public function testDeletingThePluginDataRemovesTheRecordedAddress(): void {
		$this->save( 'on' );
		$this->requests = [];

		do_action( 'graphql_delete_data' );

		$this->assertNull( $this->email_updates->get_request() );
		$this->assertFalse( get_option( self::SLUG . '_email_updates' ) );
		$this->assertSame( [], $this->requests );
	}

	public function testAStaleStoredCheckboxNeverSends(): void {
		$this->save( 'on' );
		$this->requests = [];

		// The record of the request is removed by a path that didn't update the stored section.
		delete_option( self::SLUG . '_email_updates' );

		// Re-saving the section for a different field carries the stale value along.
		update_option(
			self::SECTION,
			array_merge( get_option( self::SECTION ), [ 'some_other_field' => 'changed' ] )
		);

		$this->assertSame( [], $this->requests );
	}

	public function testSavingWithoutTheFieldsLeavesTheRequestAlone(): void {
		$this->save( 'on' );
		$this->requests = [];

		update_option( self::SECTION, [ 'some_other_field' => 'on' ] );

		$this->assertSame( [], $this->requests );
		$this->assertNotNull( $this->email_updates->get_request() );
	}

	public function testAFailedRequestIsNotRecorded(): void {
		$fail = static function () {
			return new \WP_Error( 'http_request_failed', 'Could not resolve host.' );
		};
		add_filter( 'pre_http_request', $fail, 5 );

		$this->save( 'on' );

		remove_filter( 'pre_http_request', $fail, 5 );

		$this->assertNull( $this->email_updates->get_request() );
		$this->assertContains( 'email_updates_request_failed', $this->settings_error_codes() );
		$this->assertSame( 'off', get_option( self::SECTION )[ EmailUpdates::FIELD_NAME ] );
	}

	public function testTheEndpointCanBeFiltered(): void {
		$received = [];
		$filter   = static function ( $endpoint, $hash, $slug ) use ( &$received ) {
			$received = [ $endpoint, $hash, $slug ];
			return 'https://staging.example.com/api/v1/email/subscribe';
		};
		add_filter( 'graphql_email_updates_endpoint', $filter, 10, 3 );

		$this->save( 'on' );

		remove_filter( 'graphql_email_updates_endpoint', $filter, 10 );

		$this->assertSame( [ EmailUpdates::ENDPOINT, self::HASH, self::SLUG ], $received );
		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'https://staging.example.com/api/v1/email/subscribe', $this->requests[0]['url'] );
	}

	public function testTurningOnUsageDataAloneSendsNoSubscribeRequest(): void {
		$client  = new \Appsero\Client( self::HASH, 'Email Updates Test', WPGRAPHQL_PLUGIN_FILE );
		$tracker = new UsageTracking( $client->insights(), $client->slug, self::SECTION );
		$tracker->init();
		delete_option( $client->slug . '_allow_tracking' );
		delete_option( $client->slug . '_tracking_last_send' );

		update_option(
			self::SECTION,
			[
				UsageTracking::FIELD_NAME        => 'on',
				EmailUpdates::FIELD_NAME         => 'off',
				EmailUpdates::ADDRESS_FIELD_NAME => $this->admin->user_email,
			]
		);

		remove_filter( 'pre_update_option_' . self::SECTION, [ $tracker, 'on_pre_update_option' ] );
		remove_action( 'add_option_' . $client->slug . '_allow_tracking', [ $tracker, 'sync_stored_value' ] );
		remove_action( 'update_option_' . $client->slug . '_allow_tracking', [ $tracker, 'sync_stored_value' ] );
		remove_action( 'delete_option_' . $client->slug . '_allow_tracking', [ $tracker, 'sync_stored_value' ] );

		$opted_in = $tracker->is_tracking_allowed();
		delete_option( $client->slug . '_allow_tracking' );
		delete_option( $client->slug . '_tracking_last_send' );
		wp_clear_scheduled_hook( $client->slug . '_tracker_send_event' );

		$this->assertTrue( $opted_in );
		$this->assertNotEmpty( $this->requests, 'Opting in should have sent the usage data check-in.' );
		$this->assertSame( [], $this->subscribe_requests() );
		$this->assertNull( $this->email_updates->get_request() );
	}

	public function testTheUsageDataCheckInIsUnchangedByEmailUpdates(): void {
		$client   = new \Appsero\Client( self::HASH, 'Email Updates Test', WPGRAPHQL_PLUGIN_FILE );
		$insights = $client->insights();

		$check_in = function () use ( $insights, $client ): array {
			delete_option( $client->slug . '_tracking_last_send' );
			$this->requests = [];
			$insights->send_tracking_data( true );

			// The check-in also looks up the site's IP address first, so pick out the check-in itself.
			$check_ins = array_values(
				array_filter(
					$this->requests,
					static function ( $request ) {
						return 'https://api.appsero.com/track' === $request['url'];
					}
				)
			);

			$this->assertCount( 1, $check_ins );

			return $check_ins[0]['args']['body'];
		};

		$before = $check_in();

		$this->save( 'on', 'updates-inbox@example.com' );
		$this->assertNotNull( $this->email_updates->get_request() );

		$after = $check_in();
		delete_option( $client->slug . '_tracking_last_send' );

		$this->assertSame( $before, $after );
		$this->assertSame( 'site-owner@example.com', $after['admin_email'] );
		$this->assertNotContains( 'updates-inbox@example.com', $after );
	}

	public function testPluginRegistersTheFieldsAfterTheUsageDataToggleOnTheGeneralSettings(): void {
		$registry = new \WPGraphQL\Admin\Settings\SettingsRegistry();
		do_action( 'graphql_init_settings', $registry );

		$names = wp_list_pluck( $registry->get_settings_fields()['graphql_general_settings'] ?? [], 'name' );

		$usage   = array_search( UsageTracking::FIELD_NAME, $names, true );
		$updates = array_search( EmailUpdates::FIELD_NAME, $names, true );
		$address = array_search( EmailUpdates::ADDRESS_FIELD_NAME, $names, true );

		$this->assertIsInt( $usage );
		$this->assertIsInt( $updates );
		$this->assertIsInt( $address );
		$this->assertSame( $usage + 1, $updates );
		$this->assertSame( $updates + 1, $address );
	}
}
