<?php
/**
 * Tests the IDE's email updates opt-in on the IDE Settings tab.
 *
 * @package WPGraphQLIDE
 */

use WPGraphQL\Admin\EmailUpdates;

class EmailUpdatesSettingTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * The IDE's telemetry client hash.
	 */
	private const HASH = 'e90103d6-2c09-4152-96e0-eb7d0d3b5c74';

	/**
	 * The IDE's Appsero client slug.
	 *
	 * @var string
	 */
	private $slug;

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

		$this->slug = ( new \Appsero\Client( self::HASH, 'WPGraphQL IDE', WPGRAPHQL_IDE_PLUGIN_FILE ) )->slug;

		add_filter( 'pre_http_request', [ $this, 'short_circuit_request' ], 1, 3 );

		delete_option( 'graphql_ide_settings' );
		delete_option( $this->slug . '_email_updates' );
		delete_option( $this->slug . '_allow_tracking' );
		delete_option( $this->slug . '_tracking_last_send' );
		wp_clear_scheduled_hook( $this->slug . '_tracker_send_event' );

		update_option( 'admin_email', 'site-owner@example.com' );

		$this->admin = self::factory()->user->create_and_get(
			[
				'role'       => 'administrator',
				'user_email' => 'ide-admin@example.com',
			]
		);
		wp_set_current_user( $this->admin->ID );

		$this->requests = [];
		$this->clearSchema();
	}

	public function tearDown(): void {
		remove_filter( 'pre_http_request', [ $this, 'short_circuit_request' ], 1 );

		delete_option( 'graphql_ide_settings' );
		delete_option( $this->slug . '_email_updates' );
		delete_option( $this->slug . '_allow_tracking' );
		delete_option( $this->slug . '_tracking_last_send' );
		wp_clear_scheduled_hook( $this->slug . '_tracker_send_event' );

		$this->clearSchema();
		parent::tearDown();
	}

	/**
	 * Records each request and keeps it from going over the network.
	 *
	 * @param false|array<string,mixed>|\WP_Error $preempt Whether to preempt the request.
	 * @param array<string,mixed>                $args    The request arguments.
	 * @param string                             $url     The request URL.
	 *
	 * @return array<string,mixed>
	 */
	public function short_circuit_request( $preempt, $args, $url ): array {
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
	 * Builds the settings registry the way the settings screens do, which also captures the IDE's snapshot.
	 *
	 * @return array<string,array<string,mixed>> The IDE settings fields, keyed by name.
	 */
	private function ide_fields(): array {
		$registry = new \WPGraphQL\Admin\Settings\SettingsRegistry();
		do_action( 'graphql_init_settings', $registry );

		$fields = [];
		foreach ( $registry->get_settings_fields()['graphql_ide_settings'] ?? [] as $field ) {
			$fields[ $field['name'] ] = $field;
		}

		return $fields;
	}

	/**
	 * Sets a checkbox field through the IDE's settings mutation.
	 *
	 * @return array<string,mixed>
	 */
	private function set_checkbox( string $field, bool $checked ): array {
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
						'field'   => $field,
						'value'   => [ 'checkbox' => $checked ],
					],
				],
			]
		);
	}

	public function test_fields_are_registered_after_the_usage_data_toggle_on_the_ide_settings_tab(): void {
		$fields = $this->ide_fields();
		$names  = array_keys( $fields );

		$usage   = array_search( 'share_usage_data', $names, true );
		$updates = array_search( EmailUpdates::FIELD_NAME, $names, true );

		$this->assertIsInt( $usage );
		$this->assertSame( $usage + 1, $updates );
		$this->assertSame( EmailUpdates::ADDRESS_FIELD_NAME, $names[ $updates + 1 ] ?? null );

		$this->assertSame( 'checkbox', $fields[ EmailUpdates::FIELD_NAME ]['type'] );
		$this->assertSame( 'off', $fields[ EmailUpdates::FIELD_NAME ]['value'] );
		$this->assertStringContainsString( 'WPGraphQL IDE', $fields[ EmailUpdates::FIELD_NAME ]['desc'] );
	}

	public function test_the_address_is_prefilled_with_the_current_users_email(): void {
		$fields = $this->ide_fields();

		$this->assertSame( 'ide-admin@example.com', $fields[ EmailUpdates::ADDRESS_FIELD_NAME ]['value'] );
	}

	public function test_saving_the_settings_form_with_it_on_sends_one_request_with_the_ide_hash(): void {
		$this->ide_fields();

		update_option(
			'graphql_ide_settings',
			[
				EmailUpdates::FIELD_NAME         => 'on',
				EmailUpdates::ADDRESS_FIELD_NAME => 'ide-updates@example.com',
			]
		);

		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'https://telemetry.wpgraphql.com/api/v1/email/subscribe', $this->requests[0]['url'] );
		$this->assertFalse( $this->requests[0]['args']['blocking'] );
		$this->assertSame(
			[
				'hash'           => self::HASH,
				'email'          => 'ide-updates@example.com',
				'site_url'       => home_url(),
				'plugin_version' => WPGRAPHQL_IDE_VERSION,
			],
			$this->requests[0]['args']['body']
		);
	}

	public function test_checking_the_box_in_the_ide_sends_one_request_for_the_current_user(): void {
		$this->ide_fields();

		$response = $this->set_checkbox( EmailUpdates::FIELD_NAME, true );

		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertTrue( $response['data']['updateGraphqlSetting']['success'] );

		$subscribe = $this->subscribe_requests();
		$this->assertCount( 1, $subscribe );
		$this->assertSame( 'ide-admin@example.com', $subscribe[0]['args']['body']['email'] );

		// Saving it again sends nothing more.
		$this->set_checkbox( EmailUpdates::FIELD_NAME, true );

		$this->assertCount( 1, $this->subscribe_requests() );
	}

	public function test_turning_on_usage_data_alone_sends_no_subscribe_request(): void {
		$this->ide_fields();

		$this->set_checkbox( 'share_usage_data', true );

		$this->assertSame( 'yes', get_option( $this->slug . '_allow_tracking' ) );
		$this->assertSame( [], $this->subscribe_requests() );
		$this->assertFalse( get_option( $this->slug . '_email_updates' ) );
	}

	public function test_the_usage_data_check_in_still_sends_the_site_admin_email(): void {
		$this->ide_fields();
		$this->set_checkbox( EmailUpdates::FIELD_NAME, true );
		$this->requests = [];

		$this->set_checkbox( 'share_usage_data', true );

		$check_ins = array_values(
			array_filter(
				$this->requests,
				static function ( $request ) {
					return 'https://api.appsero.com/track' === $request['url'];
				}
			)
		);

		// The check-in is the same as before: the site's admin email, not the email updates address.
		$this->assertCount( 1, $check_ins );
		$this->assertSame( 'site-owner@example.com', $check_ins[0]['args']['body']['admin_email'] );
		$this->assertSame( [], $this->subscribe_requests() );
	}

	public function test_lowering_the_settings_capability_does_not_let_editors_request_email_updates(): void {
		$lower_cap = static function (): string {
			return 'edit_others_posts';
		};
		add_filter( 'graphql_manage_settings_cap', $lower_cap );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$this->ide_fields();

		$response = $this->set_checkbox( EmailUpdates::FIELD_NAME, true );

		remove_filter( 'graphql_manage_settings_cap', $lower_cap );

		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertSame( [], $this->subscribe_requests() );
		$this->assertSame( 'off', get_option( 'graphql_ide_settings' )[ EmailUpdates::FIELD_NAME ] ?? 'off' );
	}
}
