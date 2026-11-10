<?php

use WPGraphQL\Admin\DataSharing;
use WPGraphQL\Admin\UsageTracking;

/**
 * Tests the Data Sharing settings tab and register_graphql_data_sharing_product().
 */
class DataSharingTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * The products registered before the test, restored afterwards.
	 *
	 * @var array<string,mixed>
	 */
	private $registered_products;

	/**
	 * The Appsero clients of the products the test registered, keyed by slug.
	 *
	 * @var array<string,\Appsero\Client>
	 */
	private $clients = [];

	/**
	 * The placeholder plugin folders the test created.
	 *
	 * @var string[]
	 */
	private $plugin_dirs = [];

	/**
	 * URLs of the outgoing HTTP requests made during the test.
	 *
	 * @var string[]
	 */
	private $requests = [];

	public function setUp(): void {
		parent::setUp();

		$this->registered_products = $this->get_products_property()->getValue();

		add_filter( 'pre_http_request', [ $this, 'record_request' ], 1, 3 );

		// Opting in requires the capability Appsero's own notice requires.
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		delete_option( DataSharing::SECTION );
	}

	public function tearDown(): void {
		remove_filter( 'pre_http_request', [ $this, 'record_request' ], 1 );

		// Unhook only the products this test registered, then put the registry back as it was.
		foreach ( DataSharing::get_products() as $slug => $product ) {
			if ( ! isset( $this->registered_products[ $slug ] ) ) {
				$this->unhook( $product['usage_tracking'], $slug );
			}
		}
		$this->get_products_property()->setValue( null, $this->registered_products );

		foreach ( array_keys( $this->clients ) as $slug ) {
			delete_option( $slug . '_allow_tracking' );
			delete_option( $slug . '_tracking_notice' );
			wp_clear_scheduled_hook( $slug . '_tracker_send_event' );
		}

		delete_option( DataSharing::SECTION );

		foreach ( $this->plugin_dirs as $dir ) {
			array_map( 'unlink', glob( $dir . '/*.php' ) ?: [] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		}

		parent::tearDown();
	}

	private function get_products_property(): \ReflectionProperty {
		$property = new \ReflectionProperty( DataSharing::class, 'products' );
		$property->setAccessible( true );

		return $property;
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
		$this->requests[] = $url;

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
	 * Creates an Appsero client for a test product.
	 *
	 * Appsero takes the slug from the plugin's folder name and reads the plugin file's header, so a
	 * placeholder plugin file is written for it and removed after the test.
	 */
	private function create_client( string $file_slug, string $hash ): \Appsero\Client {
		$dir  = WP_PLUGIN_DIR . '/' . $file_slug;
		$file = $dir . '/' . $file_slug . '.php';

		if ( ! file_exists( $file ) ) {
			wp_mkdir_p( $dir );
			file_put_contents( $file, "<?php\n/**\n * Plugin Name: Data Sharing Test {$file_slug}\n * Version: 1.2.3\n */\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$this->plugin_dirs[] = $dir;
		}

		$client = new \Appsero\Client( $hash, 'Data Sharing Test ' . $file_slug, $file );

		$this->clients[ $client->slug ] = $client;

		delete_option( $client->slug . '_allow_tracking' );
		wp_clear_scheduled_hook( $client->slug . '_tracker_send_event' );

		return $client;
	}

	/**
	 * Registers a test product and returns its Appsero client.
	 */
	private function register_test_product( string $file_slug, string $name ): \Appsero\Client {
		$client = $this->create_client( $file_slug, $file_slug . '-hash' );

		$registered = register_graphql_data_sharing_product(
			$client->slug,
			[
				'insights' => $client->insights(),
				'name'     => $name,
				'hash'     => $client->hash,
				'version'  => '1.2.3',
			]
		);

		$this->assertTrue( $registered );

		return $client;
	}

	private function is_allowed( \Appsero\Client $client ): bool {
		return 'yes' === get_option( $client->slug . '_allow_tracking', 'no' );
	}

	private function is_scheduled( \Appsero\Client $client ): bool {
		return false !== wp_next_scheduled( $client->slug . '_tracker_send_event' );
	}

	private function field_name( \Appsero\Client $client ): string {
		return DataSharing::get_usage_data_field_name( $client->slug );
	}

	/**
	 * Registers the fields the way the settings page does, and returns the registry.
	 */
	private function init_settings(): \WPGraphQL\Admin\Settings\SettingsRegistry {
		foreach ( DataSharing::get_products() as $slug => $product ) {
			if ( ! isset( $this->registered_products[ $slug ] ) ) {
				$product['usage_tracking']->register_field();
			}
		}

		$registry = new \WPGraphQL\Admin\Settings\SettingsRegistry();
		do_action( 'graphql_init_settings', $registry );

		return $registry;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function find_field( \WPGraphQL\Admin\Settings\SettingsRegistry $registry, string $section, string $name ): array {
		foreach ( $registry->get_settings_fields()[ $section ] ?? [] as $field ) {
			if ( $name === $field['name'] ) {
				return $field;
			}
		}

		$this->fail( sprintf( 'The field %s was not registered on %s.', $name, $section ) );
	}

	/**
	 * The slug WPGraphQL registered itself with, which is its folder name.
	 */
	private function core_slug(): string {
		foreach ( $this->registered_products as $slug => $product ) {
			if ( 'cd0d1172-95a0-4460-a36a-2c303807c9ef' === $product['hash'] ) {
				return $slug;
			}
		}

		$this->fail( 'WPGraphQL did not register itself as a data sharing product.' );
	}

	public function testRegisteringAProductRecordsItsConfig(): void {
		$client = $this->register_test_product( 'data-sharing-a', 'Product A' );

		$product = DataSharing::get_products()[ $client->slug ];

		$this->assertSame( 'Product A', $product['name'] );
		$this->assertSame( $client->slug, $product['slug'] );
		$this->assertSame( 'data-sharing-a-hash', $product['hash'] );
		$this->assertSame( '1.2.3', $product['version'] );
		$this->assertSame( 'share_usage_data_data-sharing-a', $product['usage_tracking']->get_field_name() );
	}

	public function testARegisteredProductGetsANamedToggleOnTheDataSharingTab(): void {
		$client = $this->register_test_product( 'data-sharing-a', 'Product A' );
		update_option( $client->slug . '_allow_tracking', 'yes' );

		$field = $this->find_field( $this->init_settings(), DataSharing::SECTION, $this->field_name( $client ) );

		$this->assertSame( 'checkbox', $field['type'] );
		$this->assertSame( 'Share Usage Data: Product A', $field['label'] );
		$this->assertStringContainsString( 'Product A development', $field['desc'] );
		$this->assertSame( 'on', $field['value'] );
	}

	public function testTheDataSharingTabIsRegisteredAfterTheOtherTabs(): void {
		register_graphql_settings_section( 'graphql_data_sharing_test_settings', [ 'title' => 'Another Tab' ] );

		$sections = $this->init_settings()->get_settings_sections();

		$this->assertArrayHasKey( DataSharing::SECTION, $sections );
		$this->assertSame( 'Data Sharing', $sections[ DataSharing::SECTION ]['title'] );
		$this->assertNotEmpty( $sections[ DataSharing::SECTION ]['desc'] );
		$this->assertSame( DataSharing::SECTION, array_key_last( $sections ) );
	}

	public function testCoreToggleIsOnTheDataSharingTab(): void {
		$core = $this->registered_products[ $this->core_slug() ];

		$this->assertSame( 'WPGraphQL', $core['name'] );
		$this->assertSame( WPGRAPHQL_VERSION, $core['version'] );

		$registry = new \WPGraphQL\Admin\Settings\SettingsRegistry();
		do_action( 'graphql_init_settings', $registry );

		$field = $this->find_field( $registry, DataSharing::SECTION, DataSharing::get_usage_data_field_name( $this->core_slug() ) );
		$this->assertSame( 'Share Usage Data: WPGraphQL', $field['label'] );

		$general = wp_list_pluck( $registry->get_settings_fields()['graphql_general_settings'] ?? [], 'name' );
		$this->assertNotContains( UsageTracking::FIELD_NAME, $general );
	}

	public function testCoreOptInNoticeLinksToTheDataSharingTab(): void {
		$slug = $this->core_slug();
		delete_option( $slug . '_tracking_notice' );
		update_option( $slug . '_allow_tracking', 'no' );
		$this->clients[ $slug ] = new \Appsero\Client( 'cd0d1172-95a0-4460-a36a-2c303807c9ef', 'WPGraphQL', WPGRAPHQL_PLUGIN_FILE );

		ob_start();
		do_action( 'admin_notices' );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( esc_url( admin_url( 'admin.php?page=graphql-settings#graphql_data_sharing_settings' ) ), $output );
		$this->assertStringContainsString( 'GraphQL → Settings → Data Sharing', $output );
	}

	public function testEachToggleOnlyChangesItsOwnProduct(): void {
		$a = $this->register_test_product( 'data-sharing-a', 'Product A' );
		$b = $this->register_test_product( 'data-sharing-b', 'Product B' );

		// The settings form posts every toggle on the tab.
		update_option(
			DataSharing::SECTION,
			[
				$this->field_name( $a ) => 'on',
				$this->field_name( $b ) => 'off',
			]
		);

		$this->assertTrue( $this->is_allowed( $a ) );
		$this->assertTrue( $this->is_scheduled( $a ) );
		$this->assertFalse( $this->is_allowed( $b ) );
		$this->assertFalse( $this->is_scheduled( $b ) );

		update_option(
			DataSharing::SECTION,
			[
				$this->field_name( $a ) => 'on',
				$this->field_name( $b ) => 'on',
			]
		);

		$this->assertTrue( $this->is_allowed( $a ) );
		$this->assertTrue( $this->is_allowed( $b ) );

		// Unchecking A leaves B opted in.
		update_option(
			DataSharing::SECTION,
			[
				$this->field_name( $a ) => 'off',
				$this->field_name( $b ) => 'on',
			]
		);

		$this->assertFalse( $this->is_allowed( $a ) );
		$this->assertFalse( $this->is_scheduled( $a ) );
		$this->assertTrue( $this->is_allowed( $b ) );
		$this->assertTrue( $this->is_scheduled( $b ) );

		$this->assertSame(
			[
				$this->field_name( $a ) => 'off',
				$this->field_name( $b ) => 'on',
			],
			get_option( DataSharing::SECTION )
		);
	}

	public function testOptingOutOfOneProductElsewhereOnlyUpdatesItsOwnStoredToggle(): void {
		$a = $this->register_test_product( 'data-sharing-a', 'Product A' );
		$b = $this->register_test_product( 'data-sharing-b', 'Product B' );

		update_option(
			DataSharing::SECTION,
			[
				$this->field_name( $a ) => 'on',
				$this->field_name( $b ) => 'on',
			]
		);

		// The notice's "No thanks" and the opt-out URL both end in Insights::optout().
		$a->insights()->optout();

		$this->assertFalse( $this->is_allowed( $a ) );
		$this->assertTrue( $this->is_allowed( $b ) );
		$this->assertSame(
			[
				$this->field_name( $a ) => 'off',
				$this->field_name( $b ) => 'on',
			],
			get_option( DataSharing::SECTION )
		);
	}

	public function testAnEditorCannotOptInFromTheDataSharingTab(): void {
		$a = $this->register_test_product( 'data-sharing-a', 'Product A' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		update_option( DataSharing::SECTION, [ $this->field_name( $a ) => 'on' ] );

		$this->assertFalse( $this->is_allowed( $a ) );
		$this->assertFalse( $this->is_scheduled( $a ) );
		$this->assertSame( 'off', get_option( DataSharing::SECTION )[ $this->field_name( $a ) ] );
		$this->assertSame( [], $this->requests );
	}

	public function testAnEditorCanOptOutFromTheDataSharingTab(): void {
		$a = $this->register_test_product( 'data-sharing-a', 'Product A' );
		update_option( DataSharing::SECTION, [ $this->field_name( $a ) => 'on' ] );
		$this->assertTrue( $this->is_allowed( $a ) );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		update_option( DataSharing::SECTION, [ $this->field_name( $a ) => 'off' ] );

		$this->assertFalse( $this->is_allowed( $a ) );
		$this->assertFalse( $this->is_scheduled( $a ) );
	}

	public function testARegisteredProductsDeactivationFeedbackIsGated(): void {
		$client   = $this->create_client( 'data-sharing-a', 'data-sharing-a-hash' );
		$insights = $client->insights();
		$insights->init();

		register_graphql_data_sharing_product(
			$client->slug,
			[
				'insights' => $insights,
				'name'     => 'Product A',
				'hash'     => $client->hash,
			]
		);

		$tracker = DataSharing::get_products()[ $client->slug ]['usage_tracking'];

		$this->assertFalse( has_action( 'admin_footer', [ $insights, 'deactivate_scripts' ] ) );
		$this->assertNotFalse( has_action( 'admin_footer', [ $tracker, 'maybe_deactivate_scripts' ] ) );

		$ajax_hook = 'wp_ajax_' . $client->slug . '_submit-uninstall-reason';
		$this->assertFalse( has_action( $ajax_hook, [ $insights, 'uninstall_reason_submission' ] ) );
		$this->assertNotFalse( has_action( $ajax_hook, [ $tracker, 'maybe_uninstall_reason_submission' ] ) );

		// The version is optional.
		$this->assertSame( '', DataSharing::get_products()[ $client->slug ]['version'] );
	}

	public function testRegisteringTheSameSlugTwiceIsRejected(): void {
		$this->setExpectedIncorrectUsage( 'register_graphql_data_sharing_product' );

		$a = $this->register_test_product( 'data-sharing-a', 'Product A' );

		$registered = register_graphql_data_sharing_product(
			$a->slug,
			[
				'insights' => $a->insights(),
				'name'     => 'Product A again',
				'hash'     => 'another-hash',
			]
		);

		$this->assertFalse( $registered );
		$this->assertSame( 'Product A', DataSharing::get_products()[ $a->slug ]['name'] );
	}

	/**
	 * @return array<string,array{0:string,1:array<string,mixed>}>
	 */
	public function invalidConfigProvider(): array {
		return [
			'empty slug'       => [ '', [ 'name' => 'Product', 'hash' => 'hash' ] ],
			'missing insights' => [ 'data-sharing-invalid', [ 'name' => 'Product', 'hash' => 'hash', 'insights' => null ] ],
			'wrong insights'   => [ 'data-sharing-invalid', [ 'name' => 'Product', 'hash' => 'hash', 'insights' => new \stdClass() ] ],
			'missing name'     => [ 'data-sharing-invalid', [ 'hash' => 'hash' ] ],
			'missing hash'     => [ 'data-sharing-invalid', [ 'name' => 'Product' ] ],
			'non-string name'  => [ 'data-sharing-invalid', [ 'name' => [ 'Product' ], 'hash' => 'hash' ] ],
			'non-string ver'   => [ 'data-sharing-invalid', [ 'name' => 'Product', 'hash' => 'hash', 'version' => 1 ] ],
		];
	}

	/**
	 * @dataProvider invalidConfigProvider
	 *
	 * @param array<string,mixed> $config
	 */
	public function testAnInvalidConfigIsRejected( string $slug, array $config ): void {
		$this->setExpectedIncorrectUsage( 'register_graphql_data_sharing_product' );

		if ( ! array_key_exists( 'insights', $config ) ) {
			$config['insights'] = $this->create_client( 'data-sharing-invalid', 'invalid-hash' )->insights();
		}

		$before = DataSharing::get_products();

		// @phpstan-ignore argument.type (Invalid on purpose.)
		$this->assertFalse( register_graphql_data_sharing_product( $slug, $config ) );
		$this->assertSame( $before, DataSharing::get_products() );
	}

	public function testTheUpdateRoutineRemovesTheOldGeneralSettingsCopy(): void {
		update_option(
			'graphql_general_settings',
			[
				UsageTracking::FIELD_NAME => 'on',
				'graphql_endpoint'        => 'graphql',
			]
		);

		do_action( 'graphql_do_update_routine', '2.26.2', WPGRAPHQL_VERSION );

		$this->assertSame( [ 'graphql_endpoint' => 'graphql' ], get_option( 'graphql_general_settings' ) );
	}

	public function testTheCleanupLeavesTrackingAlone(): void {
		$slug = $this->core_slug();
		$this->clients[ $slug ] = new \Appsero\Client( 'cd0d1172-95a0-4460-a36a-2c303807c9ef', 'WPGraphQL', WPGRAPHQL_PLUGIN_FILE );
		update_option( $slug . '_allow_tracking', 'yes' );
		update_option( 'graphql_general_settings', [ UsageTracking::FIELD_NAME => 'off' ] );

		DataSharing::remove_legacy_usage_data_setting();

		$this->assertSame( 'yes', get_option( $slug . '_allow_tracking' ) );
		$this->assertSame( [], get_option( 'graphql_general_settings' ) );
	}

	public function testTheCleanupDoesNothingWithoutTheOldCopy(): void {
		update_option( 'graphql_general_settings', [ 'graphql_endpoint' => 'graphql' ] );

		$writes = 0;
		$record = static function ( $option ) use ( &$writes ): void {
			if ( 'graphql_general_settings' === $option ) {
				++$writes;
			}
		};
		add_action( 'update_option', $record );

		DataSharing::remove_legacy_usage_data_setting();
		delete_option( 'graphql_general_settings' );
		DataSharing::remove_legacy_usage_data_setting();
		add_option( 'graphql_general_settings', '' );
		DataSharing::remove_legacy_usage_data_setting();

		remove_action( 'update_option', $record );

		$this->assertSame( 0, $writes );
	}
}
