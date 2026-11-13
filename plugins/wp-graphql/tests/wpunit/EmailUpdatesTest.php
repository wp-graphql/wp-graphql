<?php

use WPGraphQL\Admin\DataSharing;
use WPGraphQL\Admin\EmailUpdates;
use WPGraphQL\Admin\UsageTracking;

/**
 * Tests the email updates rows of the Data Sharing tab: one address field and one checkbox per
 * registered plugin, saved as one request to the telemetry service.
 */
class EmailUpdatesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * The plugins registered before the test, restored afterwards.
	 *
	 * @var array<string,mixed>
	 */
	private $registered_plugins;

	/**
	 * The placeholder plugin folders the test created.
	 *
	 * @var string[]
	 */
	private $plugin_dirs = [];

	/**
	 * The Appsero client slugs of the plugins the test registered.
	 *
	 * @var string[]
	 */
	private $slugs = [];

	/**
	 * The subscribe requests made during the test, as `url` and `args`.
	 *
	 * @var array<int,array{url:string,args:array<string,mixed>}>
	 */
	private $requests = [];

	/**
	 * The response the fake telemetry service gives: a status code and a body, or a WP_Error.
	 * Null means accepting every hash sent.
	 *
	 * @var array{code:int,body:array<string,mixed>}|\WP_Error|null
	 */
	private $response;

	/**
	 * @var \WP_User
	 */
	private $admin;

	public function setUp(): void {
		parent::setUp();

		require_once ABSPATH . 'wp-admin/includes/template.php';

		$this->registered_plugins = $this->get_plugins_property()->getValue();

		$this->requests = [];
		$this->response = null;
		add_filter( 'pre_http_request', [ $this, 'fake_request' ], 1, 3 );

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

		delete_option( DataSharing::SECTION );
		delete_option( EmailUpdates::REQUESTS_OPTION );
	}

	public function tearDown(): void {
		remove_filter( 'pre_http_request', [ $this, 'fake_request' ], 1 );

		foreach ( DataSharing::get_registered_plugins() as $slug => $plugin ) {
			if ( ! isset( $this->registered_plugins[ $slug ] ) ) {
				$this->unhook( $plugin['usage_tracking'], $slug );
			}
		}
		$this->get_plugins_property()->setValue( null, $this->registered_plugins );

		foreach ( $this->slugs as $slug ) {
			delete_option( $slug . '_allow_tracking' );
			delete_option( $slug . '_tracking_notice' );
			wp_clear_scheduled_hook( $slug . '_tracker_send_event' );
		}

		foreach ( $this->plugin_dirs as $dir ) {
			array_map( 'unlink', glob( $dir . '/*.php' ) ?: [] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}

		delete_option( DataSharing::SECTION );
		delete_option( EmailUpdates::REQUESTS_OPTION );
		$GLOBALS['wp_settings_errors'] = [];

		parent::tearDown();
	}

	private function get_plugins_property(): \ReflectionProperty {
		$property = new \ReflectionProperty( DataSharing::class, 'plugins' );
		$property->setAccessible( true );

		return $property;
	}

	private function unhook( UsageTracking $tracker, string $slug ): void {
		remove_filter( 'pre_update_option_' . DataSharing::SECTION, [ $tracker, 'on_pre_update_option' ] );
		remove_action( 'add_option_' . $slug . '_allow_tracking', [ $tracker, 'sync_stored_value' ] );
		remove_action( 'update_option_' . $slug . '_allow_tracking', [ $tracker, 'sync_stored_value' ] );
		remove_action( 'delete_option_' . $slug . '_allow_tracking', [ $tracker, 'sync_stored_value' ] );
		remove_action( 'graphql_register_settings', [ $tracker, 'register_field' ] );
		remove_action( 'admin_footer', [ $tracker, 'maybe_deactivate_scripts' ] );
		remove_action( 'wp_ajax_' . $slug . '_submit-uninstall-reason', [ $tracker, 'maybe_uninstall_reason_submission' ] );
	}

	/**
	 * Answers subscribe requests as the telemetry service would, and short-circuits every other
	 * request, so no test talks to the network.
	 *
	 * @param false|array<string,mixed>|\WP_Error $preempt Whether to preempt the request.
	 * @param array<string,mixed>                 $args    The request arguments.
	 * @param string                              $url     The request URL.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public function fake_request( $preempt, $args, $url ) {
		if ( EmailUpdates::ENDPOINT !== $url ) {
			return $this->http_response( 200, [] );
		}

		$this->requests[] = [
			'url'  => $url,
			'args' => $args,
		];

		if ( $this->response instanceof \WP_Error ) {
			return $this->response;
		}

		if ( null === $this->response ) {
			$sent = json_decode( (string) $args['body'], true );

			return $this->http_response(
				202,
				[
					'success'  => true,
					'message'  => 'If this address can be subscribed, a confirmation email is on its way.',
					'products' => [
						'accepted'    => $sent['hashes'],
						'unavailable' => [],
						'invalid'     => [],
					],
				]
			);
		}

		return $this->http_response( $this->response['code'], $this->response['body'] );
	}

	/**
	 * @param array<string,mixed> $body The JSON body.
	 *
	 * @return array<string,mixed>
	 */
	private function http_response( int $code, array $body ): array {
		return [
			'headers'  => [],
			'body'     => (string) wp_json_encode( $body ),
			'response' => [
				'code'    => $code,
				'message' => '',
			],
			'cookies'  => [],
		];
	}

	/**
	 * Registers a test plugin with an Appsero client of its own, and returns its slug.
	 *
	 * Appsero takes the slug from the plugin's folder name and reads the plugin file's header, so a
	 * placeholder plugin file is written for it and removed after the test.
	 */
	private function register_test_plugin( string $file_slug, string $name ): string {
		$dir  = WP_PLUGIN_DIR . '/' . $file_slug;
		$file = $dir . '/' . $file_slug . '.php';

		if ( ! file_exists( $file ) ) {
			wp_mkdir_p( $dir );
			file_put_contents( $file, "<?php\n/**\n * Plugin Name: Email Updates Test {$file_slug}\n * Version: 1.2.3\n */\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$this->plugin_dirs[] = $dir;
		}

		$client = new \Appsero\Client( $file_slug . '-hash', $name, $file );

		$this->slugs[] = $client->slug;

		$this->assertTrue(
			register_graphql_data_sharing_plugin(
				$client->slug,
				[
					'insights' => $client->insights(),
					'name'     => $name,
					'hash'     => $client->hash,
					'version'  => '1.2.3',
				]
			)
		);

		return $client->slug;
	}

	/**
	 * Saves the tab the way the settings form posts it: every given checkbox, and the address.
	 *
	 * @param array<string,string> $boxes   The checkbox state of each plugin, keyed by slug.
	 * @param string|null          $address The address, or null for the current user's.
	 */
	private function save( array $boxes, ?string $address = null ): void {
		$value = [ EmailUpdates::ADDRESS_FIELD_NAME => $address ?? $this->admin->user_email ];

		foreach ( $boxes as $slug => $state ) {
			$value[ EmailUpdates::get_field_name( $slug ) ] = $state;
		}

		update_option( DataSharing::SECTION, $value );
	}

	/**
	 * @return array<string,mixed> The JSON body of the only subscribe request made.
	 */
	private function only_request_body(): array {
		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'POST', $this->requests[0]['args']['method'] );

		return json_decode( (string) $this->requests[0]['args']['body'], true );
	}

	/**
	 * @return array<string,array{type:string,message:string}> The settings messages, keyed by code.
	 */
	private function messages(): array {
		$messages = [];

		foreach ( get_settings_errors( DataSharing::SECTION ) as $error ) {
			$messages[ $error['code'] ] = [
				'type'    => $error['type'],
				'message' => $error['message'],
			];
		}

		return $messages;
	}

	/**
	 * @return array<string,array<string,mixed>> The Data Sharing tab's fields, keyed by name, in order.
	 */
	private function fields(): array {
		foreach ( DataSharing::get_registered_plugins() as $slug => $plugin ) {
			if ( ! isset( $this->registered_plugins[ $slug ] ) ) {
				$plugin['usage_tracking']->register_field();
			}
		}

		$registry = new \WPGraphQL\Admin\Settings\SettingsRegistry();
		do_action( 'graphql_init_settings', $registry );

		$fields = [];

		foreach ( $registry->get_settings_fields()[ DataSharing::SECTION ] ?? [] as $field ) {
			$fields[ $field['name'] ] = $field;
		}

		return $fields;
	}

	public function testTheTabHasAnAddressAndACheckboxPerPluginAfterTheUsageDataRows(): void {
		$a = $this->register_test_plugin( 'email-updates-a', 'Plugin A' );
		$b = $this->register_test_plugin( 'email-updates-b', 'Plugin B' );

		$fields = $this->fields();
		$names  = array_keys( $fields );

		$heading = array_search( EmailUpdates::HEADING_FIELD_NAME, $names, true );
		$this->assertNotFalse( $heading );
		$this->assertSame( 'html', $fields[ EmailUpdates::HEADING_FIELD_NAME ]['type'] );
		$this->assertSame( 'Email Updates', $fields[ EmailUpdates::HEADING_FIELD_NAME ]['label'] );

		// Every usage data row comes before the email updates rows.
		foreach ( $names as $index => $name ) {
			if ( 0 === strpos( $name, DataSharing::USAGE_DATA_FIELD_PREFIX ) ) {
				$this->assertLessThan( $heading, $index, $name );
			}
		}

		$this->assertSame( EmailUpdates::ADDRESS_FIELD_NAME, $names[ $heading + 1 ] );

		$address = $fields[ EmailUpdates::ADDRESS_FIELD_NAME ];
		$this->assertSame( 'text', $address['type'] );
		$this->assertSame( 'Email Address', $address['label'] );
		// Empty until someone types an address, with the email address of the person viewing it
		// (not the site's admin email setting) only as a placeholder.
		$this->assertSame( '', $address['value'] );
		$this->assertSame( 'saving-admin@example.com', $address['placeholder'] );

		foreach ( [ $a => 'Plugin A', $b => 'Plugin B' ] as $slug => $name ) {
			$field = $fields[ EmailUpdates::get_field_name( $slug ) ];

			$this->assertGreaterThan( $heading + 1, array_search( $field['name'], $names, true ) );
			$this->assertSame( 'checkbox', $field['type'] );
			$this->assertSame( $name, $field['label'] );
			$this->assertSame( 'Email me about security releases and breaking changes in ' . $name, $field['desc'] );
			$this->assertSame( 'off', $field['value'] );
		}
	}

	public function testCoreHasAnEmailUpdatesCheckbox(): void {
		$core = null;

		foreach ( $this->registered_plugins as $slug => $plugin ) {
			if ( 'cd0d1172-95a0-4460-a36a-2c303807c9ef' === $plugin['hash'] ) {
				$core = $slug;
			}
		}

		$this->assertNotNull( $core, 'WPGraphQL did not register itself as a data sharing plugin.' );

		$fields = $this->fields();

		$this->assertSame( 'WPGraphQL', $fields[ EmailUpdates::get_field_name( $core ) ]['label'] );
	}

	public function testCheckingSeveralPluginsSendsOneRequestForAllOfThem(): void {
		$a = $this->register_test_plugin( 'email-updates-a', 'Plugin A' );
		$b = $this->register_test_plugin( 'email-updates-b', 'Plugin B' );
		$c = $this->register_test_plugin( 'email-updates-c', 'Plugin C' );

		$this->save(
			[
				$a => 'on',
				$b => 'off',
				$c => 'on',
			]
		);

		$body = $this->only_request_body();

		// In registration order, which is the order the confirmation email lists them in.
		$this->assertSame( [ 'email-updates-a-hash', 'email-updates-c-hash' ], $body['hashes'] );
		$this->assertArrayNotHasKey( 'hash', $body );
		$this->assertSame( 'saving-admin@example.com', $body['email'] );
		$this->assertSame( home_url(), $body['site_url'] );
		$this->assertSame( WPGRAPHQL_VERSION, $body['plugin_version'] );
		$this->assertSame( 'application/json', $this->requests[0]['args']['headers']['Content-Type'] );

		// The save waits for the response, so it can say what happened.
		$this->assertTrue( $this->requests[0]['args']['blocking'] ?? true );

		$requests = ( new EmailUpdates() )->get_requests();
		$this->assertSame( [ $a, $c ], array_keys( $requests ) );
		$this->assertSame( 'saving-admin@example.com', $requests[ $a ]['email'] );
		$this->assertEqualsWithDelta( time(), $requests[ $a ]['requested_at'], 5 );

		$stored = get_option( DataSharing::SECTION );
		$this->assertSame( 'on', $stored[ EmailUpdates::get_field_name( $a ) ] );
		$this->assertSame( 'off', $stored[ EmailUpdates::get_field_name( $b ) ] );
		$this->assertSame( 'on', $stored[ EmailUpdates::get_field_name( $c ) ] );

		$messages = $this->messages();
		$this->assertSame( 'success', $messages['email_updates_requested']['type'] );
		$this->assertStringContainsString( 'saving-admin@example.com', $messages['email_updates_requested']['message'] );
		$this->assertSame(
			'A confirmation email was requested for saving-admin@example.com for Plugin A and Plugin C updates. If the address isn&#039;t subscribed yet, it&#039;s on its way, and nothing is subscribed until you click the link in it.',
			$messages['email_updates_requested']['message']
		);
	}

	public function testACheckedBoxShowsTheRequestThatWasMade(): void {
		$a = $this->register_test_plugin( 'email-updates-a', 'Plugin A' );
		$this->save( [ $a => 'on' ], 'someone@realuser.com' );

		$fields = $this->fields();
		$field  = $fields[ EmailUpdates::get_field_name( $a ) ];

		$this->assertSame( 'on', $field['value'] );
		$this->assertStringContainsString( 'A confirmation email was requested for <strong>someone@realuser.com</strong> on ' . wp_date( get_option( 'date_format' ) ), $field['desc'] );
		$this->assertSame( 'someone@realuser.com', $fields[ EmailUpdates::ADDRESS_FIELD_NAME ]['value'] );
	}

	public function testResavingWithoutChangesSendsNothing(): void {
		$a = $this->register_test_plugin( 'email-updates-a', 'Plugin A' );
		$b = $this->register_test_plugin( 'email-updates-b', 'Plugin B' );

		$this->save(
			[
				$a => 'on',
				$b => 'off',
			]
		);
		$this->requests = [];

		// The form posts the boxes as they were shown, with the same address.
		$this->save(
			[
				$a => 'on',
				$b => 'off',
			]
		);
		$this->save( [ $a => 'on' ], 'SAVING-ADMIN@example.com ' );

		$this->assertSame( [], $this->requests );
	}

	public function testCheckingAnotherBoxOnlyRequestsTheNewOne(): void {
		$a = $this->register_test_plugin( 'email-updates-a', 'Plugin A' );
		$b = $this->register_test_plugin( 'email-updates-b', 'Plugin B' );

		$this->save(
			[
				$a => 'on',
				$b => 'off',
			]
		);
		$this->requests = [];

		$this->save(
			[
				$a => 'on',
				$b => 'on',
			]
		);

		$this->assertSame( [ 'email-updates-b-hash' ], $this->only_request_body()['hashes'] );
		$this->assertSame( [ $a, $b ], array_keys( ( new EmailUpdates() )->get_requests() ) );
	}

	public function testUncheckingClearsTheRecordAndSendsNothing(): void {
		$a = $this->register_test_plugin( 'email-updates-a', 'Plugin A' );
		$b = $this->register_test_plugin( 'email-updates-b', 'Plugin B' );

		$this->save(
			[
				$a => 'on',
				$b => 'on',
			]
		);
		$this->requests = [];

		$this->save(
			[
				$a => 'off',
				$b => 'on',
			]
		);

		$this->assertSame( [], $this->requests );
		$this->assertSame( [ $b ], array_keys( ( new EmailUpdates() )->get_requests() ) );

		$this->save(
			[
				$a => 'off',
				$b => 'off',
			]
		);

		$this->assertSame( [], $this->requests );
		$this->assertFalse( get_option( EmailUpdates::REQUESTS_OPTION ) );

		// Without a request, no address is kept: the form posts the prefilled one on every save.
		$this->assertArrayNotHasKey( EmailUpdates::ADDRESS_FIELD_NAME, get_option( DataSharing::SECTION ) );
	}

	public function testChangingTheAddressRequestsEveryCheckedPluginForTheNewAddress(): void {
		$a = $this->register_test_plugin( 'email-updates-a', 'Plugin A' );
		$b = $this->register_test_plugin( 'email-updates-b', 'Plugin B' );

		$this->save(
			[
				$a => 'on',
				$b => 'on',
			]
		);
		$this->requests = [];

		$this->save(
			[
				$a => 'on',
				$b => 'on',
			],
			'new-address@realuser.com'
		);

		$body = $this->only_request_body();
		$this->assertSame( [ 'email-updates-a-hash', 'email-updates-b-hash' ], $body['hashes'] );
		$this->assertSame( 'new-address@realuser.com', $body['email'] );

		foreach ( ( new EmailUpdates() )->get_requests() as $request ) {
			$this->assertSame( 'new-address@realuser.com', $request['email'] );
		}
	}

	public function testAPluginWithoutEmailSignupsIsReportedAndLeftUnchecked(): void {
		$a = $this->register_test_plugin( 'email-updates-a', 'Plugin A' );
		$b = $this->register_test_plugin( 'email-updates-b', 'Plugin B' );

		$this->response = [
			'code' => 202,
			'body' => [
				'success'  => true,
				'products' => [
					'accepted'    => [ 'email-updates-a-hash' ],
					'unavailable' => [ 'email-updates-b-hash' ],
					'invalid'     => [],
				],
			],
		];

		$this->save(
			[
				$a => 'on',
				$b => 'on',
			]
		);

		$this->assertSame( [ $a ], array_keys( ( new EmailUpdates() )->get_requests() ) );
		$this->assertSame( 'off', get_option( DataSharing::SECTION )[ EmailUpdates::get_field_name( $b ) ] );

		$messages = $this->messages();
		$this->assertStringContainsString( 'Plugin A', $messages['email_updates_requested']['message'] );
		$this->assertStringNotContainsString( 'Plugin B', $messages['email_updates_requested']['message'] );
		$this->assertSame( 'warning', $messages['email_updates_unavailable']['type'] );
		$this->assertSame( 'Email updates aren&#039;t available for Plugin B yet.', $messages['email_updates_unavailable']['message'] );
	}

	public function testNoPluginTakingEmailSignupsRecordsNothing(): void {
		$a = $this->register_test_plugin( 'email-updates-a', 'Plugin A' );

		$this->response = [
			'code' => 422,
			'body' => [
				'success'  => false,
				'code'     => 'email_unavailable',
				'products' => [
					'accepted'    => [],
					'unavailable' => [ 'email-updates-a-hash' ],
					'invalid'     => [],
				],
			],
		];

		$this->save( [ $a => 'on' ] );

		$this->assertSame( [], ( new EmailUpdates() )->get_requests() );
		$this->assertSame( 'off', get_option( DataSharing::SECTION )[ EmailUpdates::get_field_name( $a ) ] );

		$messages = $this->messages();
		$this->assertArrayNotHasKey( 'email_updates_requested', $messages );
		$this->assertSame( 'warning', $messages['email_updates_unavailable']['type'] );
	}

	public function testAPluginTheServiceDoesNotKnowIsReported(): void {
		$a = $this->register_test_plugin( 'email-updates-a', 'Plugin A' );

		$this->response = [
			'code' => 401,
			'body' => [
				'success'  => false,
				'products' => [
					'accepted'    => [],
					'unavailable' => [],
					'invalid'     => [ 'email-updates-a-hash' ],
				],
			],
		];

		$this->save( [ $a => 'on' ] );

		$this->assertSame( [], ( new EmailUpdates() )->get_requests() );

		$messages = $this->messages();
		$this->assertSame( 'error', $messages['email_updates_unknown_plugin']['type'] );
		$this->assertStringContainsString( 'Plugin A', $messages['email_updates_unknown_plugin']['message'] );
	}

	/**
	 * @return array<string,array{0:array{code:int,body:array<string,mixed>}|\WP_Error,1:string}>
	 */
	public function failedResponseProvider(): array {
		return [
			'rate limited'                     => [
				[
					'code' => 429,
					'body' => [ 'message' => 'Too many requests.' ],
				],
				'email_updates_rate_limited',
			],
			'address rejected'                 => [
				[
					'code' => 422,
					'body' => [
						'success' => false,
						'message' => 'The email field must be a valid email address.',
						'errors'  => [ 'email' => [ 'The email field must be a valid email address.' ] ],
					],
				],
				'email_updates_address_rejected',
			],
			'server error'                     => [
				[
					'code' => 500,
					'body' => [],
				],
				'email_updates_request_failed',
			],
			'accepted without saying for what' => [
				[
					'code' => 202,
					'body' => [ 'success' => true ],
				],
				'email_updates_request_failed',
			],
			'a hash left out of the lists'     => [
				[
					'code' => 202,
					'body' => [
						'success'  => true,
						'products' => [
							'accepted'    => [ 'email-updates-a-hash' ],
							'unavailable' => [],
							'invalid'     => [],
						],
					],
				],
				'email_updates_request_failed',
			],
			'network error'                    => [
				new \WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ),
				'email_updates_request_failed',
			],
		];
	}

	/**
	 * @dataProvider failedResponseProvider
	 *
	 * @param array{code:int,body:array<string,mixed>}|\WP_Error $response The service's response.
	 * @param string                                             $code     The expected message code.
	 */
	public function testAFailedRequestRecordsNothing( $response, string $code ): void {
		$a = $this->register_test_plugin( 'email-updates-a', 'Plugin A' );
		$b = $this->register_test_plugin( 'email-updates-b', 'Plugin B' );

		$this->response = $response;

		$this->save(
			[
				$a => 'on',
				$b => 'on',
			]
		);

		$this->assertCount( 1, $this->requests );
		$this->assertSame( [], ( new EmailUpdates() )->get_requests() );
		$this->assertSame( 'off', get_option( DataSharing::SECTION )[ EmailUpdates::get_field_name( $a ) ] );

		$messages = $this->messages();
		$this->assertSame( [ $code ], array_keys( $messages ) );
		$this->assertSame( 'error', $messages[ $code ]['type'] );

		// Saving again tries again, since nothing was recorded.
		$this->response = null;
		$this->save(
			[
				$a => 'on',
				$b => 'on',
			]
		);

		$this->assertCount( 2, $this->requests );
		$this->assertSame( [ $a, $b ], array_keys( ( new EmailUpdates() )->get_requests() ) );
	}

	public function testAFailedRequestSaysWhy(): void {
		$a = $this->register_test_plugin( 'email-updates-a', 'Plugin A' );

		$this->response = new \WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
		$this->save( [ $a => 'on' ] );

		$message = $this->messages()['email_updates_request_failed']['message'];
		$this->assertStringContainsString( 'cURL error 28: Operation timed out', $message );
		// The service may have got the request even though the answer didn't arrive.
		$this->assertStringContainsString( 'it isn&#039;t known whether the confirmation email', $message );

		// The request waits long enough for a service that is slow to answer.
		$this->assertSame( 15, $this->requests[0]['args']['timeout'] );

		$GLOBALS['wp_settings_errors'] = [];
		$this->response                = [
			'code' => 500,
			'body' => [],
		];
		$this->save( [ $a => 'off' ] );
		$this->save( [ $a => 'on' ] );

		$this->assertStringContainsString( '(HTTP 500)', $this->messages()['email_updates_request_failed']['message'] );
	}

	public function testAnEditorCannotSignUpButCanUncheck(): void {
		$a = $this->register_test_plugin( 'email-updates-a', 'Plugin A' );
		$this->save( [ $a => 'on' ] );
		$this->requests = [];

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$this->save( [ $a => 'on' ], 'editor@realuser.com' );

		$this->assertSame( [], $this->requests );
		$this->assertArrayHasKey( 'email_updates_not_allowed', $this->messages() );
		$this->assertSame( 'saving-admin@example.com', ( new EmailUpdates() )->get_requests()[ $a ]['email'] );

		$this->save( [ $a => 'off' ] );

		$this->assertSame( [], $this->requests );
		$this->assertSame( [], ( new EmailUpdates() )->get_requests() );
	}

	public function testASaveWithoutAUserSendsNothing(): void {
		$a = $this->register_test_plugin( 'email-updates-a', 'Plugin A' );

		wp_set_current_user( 0 );

		$this->save( [ $a => 'on' ], 'someone@realuser.com' );

		$this->assertSame( [], $this->requests );
		$this->assertSame( [], ( new EmailUpdates() )->get_requests() );
	}

	public function testAnInvalidAddressSendsNothing(): void {
		$a = $this->register_test_plugin( 'email-updates-a', 'Plugin A' );

		$this->save( [ $a => 'on' ], 'not an address' );

		$this->assertSame( [], $this->requests );
		$this->assertArrayHasKey( 'email_updates_invalid_email', $this->messages() );
	}

	public function testAnEmptyAddressSendsNothing(): void {
		$a = $this->register_test_plugin( 'email-updates-a', 'Plugin A' );

		// The field shows the user's address only as a placeholder, so it's never used on its own.
		$this->save( [ $a => 'on' ], '' );

		$this->assertSame( [], $this->requests );
		$this->assertSame( [], ( new EmailUpdates() )->get_requests() );
		$this->assertArrayHasKey( 'email_updates_invalid_email', $this->messages() );
	}

	public function testAStaleStoredValueNeverSignsUpOnItsOwn(): void {
		$a = $this->register_test_plugin( 'email-updates-a', 'Plugin A' );

		// A stored "on" with no request behind it, from before the record was removed.
		update_option(
			DataSharing::SECTION,
			[
				EmailUpdates::get_field_name( $a ) => 'on',
				EmailUpdates::ADDRESS_FIELD_NAME   => 'saving-admin@example.com',
			]
		);
		$this->requests = [];
		delete_option( EmailUpdates::REQUESTS_OPTION );

		// Another field is saved, carrying the stale value along.
		$this->save( [ $a => 'on' ] );

		$this->assertSame( [], $this->requests );
	}

	public function testASaveWithoutTheEmailFieldsLeavesThemAlone(): void {
		$a = $this->register_test_plugin( 'email-updates-a', 'Plugin A' );
		$this->save( [ $a => 'on' ] );
		$this->requests = [];

		update_option( DataSharing::SECTION, [ DataSharing::get_usage_data_field_name( $a ) => 'off' ] );

		$this->assertSame( [], $this->requests );
		$this->assertSame( [ $a ], array_keys( ( new EmailUpdates() )->get_requests() ) );
	}

	public function testTheEndpointCanBeFiltered(): void {
		$a = $this->register_test_plugin( 'email-updates-a', 'Plugin A' );

		$filter = static function ( $endpoint, $hashes ) {
			return 'https://telemetry.test/api/v1/email/subscribe?for=' . implode( ',', $hashes );
		};
		add_filter( 'graphql_email_updates_endpoint', $filter, 10, 2 );

		$urls = [];
		$spy  = static function ( $preempt, $args, $url ) use ( &$urls ) {
			$urls[] = $url;
			return $preempt;
		};
		add_filter( 'pre_http_request', $spy, 0, 3 );

		$this->save( [ $a => 'on' ] );

		remove_filter( 'graphql_email_updates_endpoint', $filter, 10 );
		remove_filter( 'pre_http_request', $spy, 0 );

		$this->assertContains( 'https://telemetry.test/api/v1/email/subscribe?for=email-updates-a-hash', $urls );
	}

	public function testDeletingWPGraphQLDataRemovesTheRecords(): void {
		$a = $this->register_test_plugin( 'email-updates-a', 'Plugin A' );
		$this->save( [ $a => 'on' ] );

		$this->assertNotFalse( get_option( EmailUpdates::REQUESTS_OPTION ) );

		do_action( 'graphql_delete_data' );

		$this->assertFalse( get_option( EmailUpdates::REQUESTS_OPTION ) );
	}
}
