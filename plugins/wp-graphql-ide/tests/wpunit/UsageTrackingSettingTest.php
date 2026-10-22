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

	public function setUp(): void {
		parent::setUp();

		$this->slug = ( new \Appsero\Client( 'usage-tracking-test-hash', 'WPGraphQL IDE', WPGRAPHQL_IDE_PLUGIN_FILE ) )->slug;

		add_filter( 'pre_http_request', [ $this, 'short_circuit_request' ], 1 );

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
	 * Keeps opting in from sending a check-in over the network.
	 *
	 * @return array<string,mixed>
	 */
	public function short_circuit_request(): array {
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

	public function test_lowering_the_settings_capability_does_not_let_editors_opt_in(): void {
		$lower_cap = static function (): string {
			return 'edit_others_posts';
		};
		add_filter( 'graphql_manage_settings_cap', $lower_cap );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		$this->usage_tracking_field();

		$response = $this->set_toggle( true );

		remove_filter( 'graphql_manage_settings_cap', $lower_cap );

		// The save itself goes through; only the opt-in is held back.
		$this->assertArrayNotHasKey( 'errors', $response );
		$this->assertTrue( $response['data']['updateGraphqlSetting']['success'] );

		$this->assertNotSame( 'yes', get_option( $this->slug . '_allow_tracking' ) );
		$this->assertFalse( wp_next_scheduled( $this->slug . '_tracker_send_event' ) );
	}
}
