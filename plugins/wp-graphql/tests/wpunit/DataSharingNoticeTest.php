<?php

use WPGraphQL\Admin\DataSharing;
use WPGraphQL\Admin\DataSharingNotice;
use WPGraphQL\Admin\EmailUpdates;
use WPGraphQL\Admin\UsageTracking;

/**
 * Tests the Data Sharing notice, which asks about usage data and email updates in place of each
 * plugin's own Appsero notice.
 */
class DataSharingNoticeTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

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
	 * The Appsero insights instances of the plugins the test registered, keyed by slug.
	 *
	 * @var array<string,\Appsero\Insights>
	 */
	private $insights = [];

	/**
	 * The bodies of the subscribe requests made during the test.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	private $subscribe_requests = [];

	/**
	 * @var \WPGraphQL\Admin\DataSharingNotice
	 */
	private $notice;

	/**
	 * @var \WP_User
	 */
	private $admin;

	public function setUp(): void {
		parent::setUp();

		require_once ABSPATH . 'wp-admin/includes/template.php';

		// Only the plugins the test registers, so WPGraphQL's own state doesn't change what's shown.
		$this->registered_plugins = $this->get_plugins_property()->getValue();
		$this->get_plugins_property()->setValue( null, [] );

		$this->notice = new DataSharingNotice( new EmailUpdates() );

		$this->subscribe_requests = [];
		add_filter( 'pre_http_request', [ $this, 'fake_request' ], 1, 3 );

		$this->admin = self::factory()->user->create_and_get(
			[
				'role'       => 'administrator',
				'user_email' => 'notice-admin@realuser.com',
			]
		);
		wp_set_current_user( $this->admin->ID );

		delete_option( EmailUpdates::REQUESTS_OPTION );
		unset( $_GET[ DataSharingNotice::QUERY_ARG ] );
	}

	public function tearDown(): void {
		remove_filter( 'pre_http_request', [ $this, 'fake_request' ], 1 );

		foreach ( DataSharing::get_registered_plugins() as $slug => $plugin ) {
			$this->unhook( $plugin['usage_tracking'], $slug );
			delete_option( $slug . '_allow_tracking' );
			delete_option( $slug . '_tracking_notice' );
			delete_option( $slug . '_tracking_last_send' );
			wp_clear_scheduled_hook( $slug . '_tracker_send_event' );
		}
		$this->get_plugins_property()->setValue( null, $this->registered_plugins );

		foreach ( $this->plugin_dirs as $dir ) {
			array_map( 'unlink', glob( $dir . '/*.php' ) ?: [] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}

		delete_option( EmailUpdates::REQUESTS_OPTION );
		unset( $_GET[ DataSharingNotice::QUERY_ARG ], $_POST['choice'], $_POST['plugins'], $_POST['address'], $_REQUEST['_wpnonce'], $_REQUEST['_wp_http_referer'] );

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
	 * Accepts every subscribe request, and short-circuits every other request, so no test talks to the network.
	 *
	 * @param false|array<string,mixed>|\WP_Error $preempt Whether to preempt the request.
	 * @param array<string,mixed>                 $args    The request arguments.
	 * @param string                              $url     The request URL.
	 *
	 * @return array<string,mixed>
	 */
	public function fake_request( $preempt, $args, $url ) {
		$body = [];

		if ( EmailUpdates::ENDPOINT === $url ) {
			$sent                       = json_decode( (string) $args['body'], true );
			$this->subscribe_requests[] = $sent;

			$body = [
				'success'  => true,
				'products' => [
					'accepted'    => $sent['hashes'],
					'unavailable' => [],
					'invalid'     => [],
				],
			];
		}

		return [
			'headers'  => [],
			'body'     => (string) wp_json_encode( $body ),
			'response' => [
				'code'    => EmailUpdates::ENDPOINT === $url ? 202 : 200,
				'message' => '',
			],
			'cookies'  => [],
		];
	}

	/**
	 * Registers a test plugin the way a plugin does once Appsero is initialized, and returns its slug.
	 */
	private function register_test_plugin( string $file_slug, string $name ): string {
		$dir  = WP_PLUGIN_DIR . '/' . $file_slug;
		$file = $dir . '/' . $file_slug . '.php';

		if ( ! file_exists( $file ) ) {
			wp_mkdir_p( $dir );
			file_put_contents( $file, "<?php\n/**\n * Plugin Name: Data Sharing Notice Test {$file_slug}\n * Version: 1.2.3\n */\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$this->plugin_dirs[] = $dir;
		}

		$client   = new \Appsero\Client( $file_slug . '-hash', $name, $file );
		$insights = $client->insights();
		$insights->init();

		delete_option( $client->slug . '_allow_tracking' );
		delete_option( $client->slug . '_tracking_notice' );

		$this->assertTrue(
			register_graphql_data_sharing_plugin(
				$client->slug,
				[
					'insights' => $insights,
					'name'     => $name,
					'hash'     => $client->hash,
					'version'  => '1.2.3',
				]
			)
		);

		$this->insights[ $client->slug ] = $insights;

		return $client->slug;
	}

	private function render(): string {
		ob_start();
		$this->notice->render();

		return (string) ob_get_clean();
	}

	/**
	 * Posts a form of the notice, the way the browser does from the given page.
	 *
	 * @param string              $action The admin-post action.
	 * @param array<string,mixed> $fields The posted fields.
	 */
	private function post( string $action, array $fields ): void {
		foreach ( $fields as $key => $value ) {
			$_POST[ $key ] = $value;
		}

		$_REQUEST['_wpnonce']         = wp_create_nonce( $action );
		$_REQUEST['_wp_http_referer'] = '/wp-admin/plugins.php?' . DataSharingNotice::QUERY_ARG . '=decided';
	}

	private function is_allowed( string $slug ): bool {
		return 'yes' === get_option( $slug . '_allow_tracking', 'no' );
	}

	public function testOneNoticeAsksBothQuestionsInPlaceOfEachAppseroNotice(): void {
		$a = $this->register_test_plugin( 'notice-a', 'Plugin A' );
		$b = $this->register_test_plugin( 'notice-b', 'Plugin B' );

		foreach ( [ $a, $b ] as $slug ) {
			$this->assertFalse( has_action( 'admin_notices', [ $this->insights[ $slug ], 'admin_notice' ] ), $slug );
		}

		$html = $this->render();

		$this->assertSame( 1, substr_count( $html, 'id="graphql-data-sharing-notice"' ) );
		$this->assertStringContainsString( 'Help improve Plugin A and Plugin B?', $html );
		$this->assertStringContainsString( 'this site&#039;s admin email address and an administrator&#039;s name. It doesn&#039;t sign anyone up for emails.', $html );
		$this->assertStringContainsString( 'value="allow"', $html );
		$this->assertStringContainsString( 'value="decline"', $html );

		// The email question has its own form, plugin checkboxes that start unchecked, and an empty
		// address field that suggests the user's address without filling it in.
		$this->assertSame( 2, substr_count( $html, '<form ' ) );
		$this->assertStringContainsString( 'name="plugins[]" value="' . $a . '"', $html );
		$this->assertStringContainsString( 'name="plugins[]" value="' . $b . '"', $html );
		$this->assertStringNotContainsString( 'checked', $html );
		$this->assertStringContainsString( 'placeholder="notice-admin@realuser.com"', $html );
		$this->assertStringNotContainsString( 'value="notice-admin@realuser.com"', $html );
	}

	public function testOnlyAdministratorsSeeIt(): void {
		$this->register_test_plugin( 'notice-a', 'Plugin A' );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$this->assertSame( '', $this->render() );
	}

	public function testItOnlyAsksAboutPluginsThatHaveNotChosen(): void {
		$a = $this->register_test_plugin( 'notice-a', 'Plugin A' );
		$b = $this->register_test_plugin( 'notice-b', 'Plugin B' );
		update_option( $b . '_tracking_notice', 'hide' );

		$this->assertStringContainsString( 'Help improve Plugin A?', $this->render() );

		update_option( $a . '_allow_tracking', 'yes' );

		// Nothing left to ask about usage data, and the email question only comes back right after a choice.
		$this->assertSame( '', $this->render() );
	}

	public function testAfterTheUsageChoiceTheEmailQuestionShowsOnce(): void {
		$a = $this->register_test_plugin( 'notice-a', 'Plugin A' );
		update_option( $a . '_tracking_notice', 'hide' );

		$_GET[ DataSharingNotice::QUERY_ARG ] = 'decided';
		$html                                 = $this->render();

		$this->assertStringContainsString( 'Your choice was saved. Want email updates too?', $html );
		$this->assertStringNotContainsString( 'value="allow"', $html );
		// With one plugin there's nothing to choose, so the button is the choice.
		$this->assertStringContainsString( 'Email me about security releases and breaking changes in Plugin A.', $html );
		$this->assertStringContainsString( 'type="hidden" name="plugins[]" value="' . $a . '"', $html );
	}

	public function testAllowOptsInEveryPluginThatHasNotChosen(): void {
		$a = $this->register_test_plugin( 'notice-a', 'Plugin A' );
		$b = $this->register_test_plugin( 'notice-b', 'Plugin B' );
		$c = $this->register_test_plugin( 'notice-c', 'Plugin C' );
		update_option( $c . '_tracking_notice', 'hide' );

		$this->post( DataSharingNotice::USAGE_ACTION, [ 'choice' => 'allow' ] );
		$url = $this->notice->process_usage();

		$this->assertTrue( $this->is_allowed( $a ) );
		$this->assertTrue( $this->is_allowed( $b ) );
		// A plugin that already declined isn't opted in by someone else's Allow.
		$this->assertFalse( $this->is_allowed( $c ) );

		$this->assertSame( '/wp-admin/plugins.php?' . DataSharingNotice::QUERY_ARG . '=decided', $url );

		// Allowing doesn't sign anyone up for email.
		$this->assertSame( [], $this->subscribe_requests );
	}

	public function testNoThanksDeclinesWithoutOptingIn(): void {
		$a = $this->register_test_plugin( 'notice-a', 'Plugin A' );

		$this->post( DataSharingNotice::USAGE_ACTION, [ 'choice' => 'decline' ] );
		$this->notice->process_usage();

		$this->assertFalse( $this->is_allowed( $a ) );
		$this->assertSame( 'hide', get_option( $a . '_tracking_notice' ) );
		$this->assertSame( [], $this->notice->get_undecided_plugins() );
	}

	public function testTheEmailFormSendsOneRequestForTheChosenPlugins(): void {
		$a = $this->register_test_plugin( 'notice-a', 'Plugin A' );
		$b = $this->register_test_plugin( 'notice-b', 'Plugin B' );
		$c = $this->register_test_plugin( 'notice-c', 'Plugin C' );

		$this->post(
			DataSharingNotice::EMAIL_ACTION,
			[
				'plugins' => [ $c, $a ],
				'address' => 'someone@realuser.com',
			]
		);
		$url = $this->notice->process_email();

		$this->assertCount( 1, $this->subscribe_requests );
		// In registration order, whatever order the form posted them in.
		$this->assertSame( [ 'notice-a-hash', 'notice-c-hash' ], $this->subscribe_requests[0]['hashes'] );
		$this->assertSame( 'someone@realuser.com', $this->subscribe_requests[0]['email'] );

		$this->assertSame( [ $a, $c ], array_keys( ( new EmailUpdates() )->get_requests() ) );
		$this->assertSame( [ $b ], array_keys( $this->notice->get_email_plugins() ) );

		// Usage data is a separate choice.
		$this->assertFalse( $this->is_allowed( $a ) );

		// The next page says what happened, once.
		$this->assertStringContainsString( DataSharingNotice::QUERY_ARG . '=emailed', $url );
		$_GET[ DataSharingNotice::QUERY_ARG ] = 'emailed';

		$html = $this->render();
		$this->assertStringContainsString( 'notice-success', $html );
		$this->assertStringContainsString( 'A confirmation email was requested for someone@realuser.com for Plugin A and Plugin C updates.', $html );
		// The usage data question is still open, but the email question was just answered.
		$this->assertStringContainsString( 'value="allow"', $html );
		$this->assertStringNotContainsString( 'name="plugins[]"', $html );

		$this->assertStringNotContainsString( 'A confirmation email was requested', $this->render() );
	}

	public function testTheEmailFormWithoutAPluginSendsNothing(): void {
		$this->register_test_plugin( 'notice-a', 'Plugin A' );

		$this->post( DataSharingNotice::EMAIL_ACTION, [ 'address' => 'someone@realuser.com' ] );
		$this->notice->process_email();

		$this->assertSame( [], $this->subscribe_requests );

		$_GET[ DataSharingNotice::QUERY_ARG ] = 'emailed';
		$this->assertStringContainsString( 'Choose at least one plugin and try again.', $this->render() );
	}

	public function testDismissingStopsAskingOnlyThatUserAboutEmail(): void {
		$a = $this->register_test_plugin( 'notice-a', 'Plugin A' );

		$this->post( DataSharingNotice::DISMISS_ACTION, [] );
		$this->notice->process_dismiss();

		$this->assertSame( [], $this->notice->get_email_plugins() );

		$html = $this->render();
		$this->assertStringContainsString( 'value="allow"', $html );
		$this->assertStringNotContainsString( 'name="plugins[]"', $html );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertSame( [ $a ], array_keys( $this->notice->get_email_plugins() ) );
	}

	public function testTheFormsNeedManageOptions(): void {
		$a = $this->register_test_plugin( 'notice-a', 'Plugin A' );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$this->post( DataSharingNotice::USAGE_ACTION, [ 'choice' => 'allow' ] );

		try {
			$this->notice->process_usage();
			$this->fail( 'Expected the request to be refused.' );
		} catch ( \WPDieException $e ) {
			$this->assertFalse( $this->is_allowed( $a ) );
		}
	}

	public function testTheFormsNeedAValidNonce(): void {
		$a = $this->register_test_plugin( 'notice-a', 'Plugin A' );

		$this->post( DataSharingNotice::USAGE_ACTION, [ 'choice' => 'allow' ] );
		$_REQUEST['_wpnonce'] = wp_create_nonce( DataSharingNotice::EMAIL_ACTION );

		try {
			$this->notice->process_usage();
			$this->fail( 'Expected the request to be refused.' );
		} catch ( \WPDieException $e ) {
			$this->assertFalse( $this->is_allowed( $a ) );
		}
	}
}
