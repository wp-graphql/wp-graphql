<?php

use WPGraphQL\Admin\Settings\Settings;
use WPGraphQL\Admin\SettingsReview\SettingsReview;

/**
 * Tests for the settings review: which settings it shows, when it invites administrators, and saving.
 */
class SettingsReviewTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * The settings manager the review under test reads from.
	 *
	 * @var \WPGraphQL\Admin\Settings\Settings
	 */
	private $settings;

	/**
	 * @var int
	 */
	private $admin;

	/**
	 * @var int
	 */
	private $subscriber;

	/**
	 * The core settings in the review.
	 */
	private const CORE_KEYS = [
		'graphql_general_settings.restrict_endpoint_to_logged_in_users',
		'graphql_general_settings.public_introspection_enabled',
		'graphql_general_settings.batch_queries_enabled',
		'graphql_general_settings.batch_limit',
		'graphql_general_settings.query_depth_enabled',
		'graphql_general_settings.query_depth_max',
		'graphql_general_settings.debug_mode_enabled',
		'graphql_general_settings.tracing_enabled',
		'graphql_general_settings.tracing_user_role',
		'graphql_general_settings.query_logs_enabled',
		'graphql_general_settings.query_log_user_role',
	];

	public function setUp(): void {
		parent::setUp();

		$this->admin      = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		$this->subscriber = $this->factory()->user->create( [ 'role' => 'subscriber' ] );

		delete_option( SettingsReview::STATE_OPTION );
		delete_option( 'graphql_general_settings' );
		delete_option( 'graphql_review_test_settings' );

		$this->settings = new Settings();
		$this->settings->init();
		$this->settings->register_settings();
	}

	public function tearDown(): void {
		delete_option( SettingsReview::STATE_OPTION );
		delete_option( 'graphql_general_settings' );
		delete_option( 'graphql_review_test_settings' );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * Returns a review reading from the test settings registry, after any extra registration.
	 *
	 * @param callable|null $register Registers extra sections and fields on the registry.
	 */
	private function get_settings_review( ?callable $register = null ): SettingsReview {
		if ( null !== $register ) {
			$register( $this->settings->settings_api );
		}

		$this->settings->settings_api->init_registry();

		return new SettingsReview( $this->settings->settings_api );
	}

	/**
	 * Returns a value for every core setting in the review.
	 *
	 * @param array<string,mixed> $overrides Values to use instead of the defaults.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function core_settings( array $overrides = [] ): array {
		return [
			'graphql_general_settings' => array_merge(
				[
					'restrict_endpoint_to_logged_in_users' => 'off',
					'public_introspection_enabled'         => 'off',
					'batch_queries_enabled'                => 'on',
					'batch_limit'                          => 10,
					'query_depth_enabled'                  => 'on',
					'query_depth_max'                      => 15,
					'debug_mode_enabled'                   => 'off',
					'tracing_enabled'                      => 'off',
					'tracing_user_role'                    => 'administrator',
					'query_logs_enabled'                   => 'off',
					'query_log_user_role'                  => 'administrator',
				],
				$overrides
			),
		];
	}

	/**
	 * Saves the review the way its REST API route does.
	 *
	 * The tests call the route's callback directly rather than dispatching a REST request, because
	 * starting the REST server leaves state behind that breaks later tests in the suite.
	 *
	 * @param array<string,mixed> $params The request parameters.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function save( array $params ) {
		$request = new \WP_REST_Request( 'POST', '/' . SettingsReview::REST_NAMESPACE . SettingsReview::REST_ROUTE );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $this->get_settings_review()->save( $request );
	}

	public function testCoreSettingsAreShownInTheirSteps(): void {
		$settings_review = $this->get_settings_review();
		$fields = $settings_review->get_fields();

		foreach ( self::CORE_KEYS as $key ) {
			$this->assertArrayHasKey( $key, $fields );
		}

		$this->assertSame( 'request-limits', $fields['graphql_general_settings.query_depth_enabled']['step'] );
		$this->assertSame( 'graphql_general_settings.query_depth_enabled', $fields['graphql_general_settings.query_depth_max']['dependsOn'] );
		$this->assertArrayNotHasKey( 'recommended', $fields['graphql_general_settings.query_depth_enabled'] );
		$this->assertNotEmpty( $fields['graphql_general_settings.query_depth_enabled']['benefits'] );
		$this->assertNotEmpty( $fields['graphql_general_settings.query_depth_enabled']['costs'] );

		// Settings that aren't opted in stay out of the review.
		$this->assertArrayNotHasKey( 'graphql_general_settings.graphql_endpoint', $fields );

		$steps = wp_list_pluck( $settings_review->get_steps(), 'slug' );
		$this->assertSame( [ 'access', 'request-limits', 'diagnostics' ], $steps );
	}

	public function testSettingWithoutARegisteredStepIsShownInAStepForItsSection(): void {
		$settings_review = $this->get_settings_review(
			static function ( $registry ) {
				$registry->register_section( 'graphql_review_test_settings', [ 'title' => 'Review Test Settings' ] );
				$registry->register_field(
					'graphql_review_test_settings',
					[
						'name'         => 'test_toggle',
						'label'        => 'Test toggle',
						'type'         => 'checkbox',
						'default'      => 'off',
						'settings_review' => true,
					]
				);
			}
		);

		$steps = $settings_review->get_steps();
		$last  = end( $steps );

		$this->assertSame( 'graphql_review_test_settings', $last['slug'] );
		$this->assertSame( 'Review Test Settings', $last['title'] );
		$this->assertSame( [ 'graphql_review_test_settings.test_toggle' ], $last['fields'] );
	}

	public function testPluginsCanRegisterSteps(): void {
		register_graphql_settings_review_step(
			'review-test',
			[
				'title'       => 'Review test step',
				'description' => 'A step registered by a plugin.',
				'order'       => 5,
			]
		);

		$settings_review = $this->get_settings_review(
			static function ( $registry ) {
				$registry->register_section( 'graphql_review_test_settings', [ 'title' => 'Review Test Settings' ] );
				$registry->register_field(
					'graphql_review_test_settings',
					[
						'name'         => 'test_toggle',
						'label'        => 'Test toggle',
						'type'         => 'checkbox',
						'settings_review' => [ 'step' => 'review-test' ],
					]
				);
			}
		);

		$steps = $settings_review->get_steps();

		$this->assertSame( 'review-test', $steps[0]['slug'] );
		$this->assertSame( 'Review test step', $steps[0]['title'] );
		$this->assertSame( [ 'graphql_review_test_settings.test_toggle' ], $steps[0]['fields'] );
	}

	public function testUnsupportedFieldTypesAreLeftOut(): void {
		$this->setExpectedIncorrectUsage( 'register_graphql_settings_field' );

		$settings_review = $this->get_settings_review(
			static function ( $registry ) {
				$registry->register_section( 'graphql_review_test_settings', [ 'title' => 'Review Test Settings' ] );
				$registry->register_field(
					'graphql_review_test_settings',
					[
						'name'         => 'test_color',
						'label'        => 'Test color',
						'type'         => 'color',
						'settings_review' => true,
					]
				);
			}
		);

		$this->assertArrayNotHasKey( 'graphql_review_test_settings.test_color', $settings_review->get_fields() );
	}

	public function testValuesComeFromSavedSettingsOrDefaults(): void {
		update_option(
			'graphql_general_settings',
			[
				'query_depth_enabled' => 'on',
				'query_depth_max'     => 12,
			]
		);

		$values = $this->get_settings_review()->get_values();

		$this->assertSame( 'on', $values['graphql_general_settings.query_depth_enabled'] );
		$this->assertSame( 12, $values['graphql_general_settings.query_depth_max'] );
		$this->assertSame( 'on', $values['graphql_general_settings.batch_queries_enabled'] );
		$this->assertSame( 'administrator', $values['graphql_general_settings.tracing_user_role'] );
	}

	public function testAdministratorsAreInvitedUntilEverySettingIsReviewed(): void {
		$settings_review = $this->get_settings_review();

		wp_set_current_user( $this->subscriber );
		$this->assertFalse( $settings_review->should_invite() );

		wp_set_current_user( $this->admin );
		$this->assertTrue( $settings_review->should_invite() );

		update_option(
			SettingsReview::STATE_OPTION,
			[
				'status'   => 'skipped',
				'reviewed' => array_keys( $settings_review->get_fields() ),
			]
		);

		$this->assertFalse( $settings_review->should_invite() );
	}

	public function testANewSettingInvitesAdministratorsAgain(): void {
		update_option(
			SettingsReview::STATE_OPTION,
			[
				'status'   => 'completed',
				'reviewed' => self::CORE_KEYS,
			]
		);

		$settings_review = $this->get_settings_review(
			static function ( $registry ) {
				$registry->register_section( 'graphql_review_test_settings', [ 'title' => 'Review Test Settings' ] );
				$registry->register_field(
					'graphql_review_test_settings',
					[
						'name'         => 'test_toggle',
						'label'        => 'Test toggle',
						'type'         => 'checkbox',
						'settings_review' => true,
					]
				);
			}
		);

		wp_set_current_user( $this->admin );

		$this->assertTrue( $settings_review->should_invite() );
		$this->assertSame( [ 'graphql_review_test_settings.test_toggle' ], $settings_review->get_unreviewed_field_keys() );
	}

	public function testSavingRequiresPermissionToManageOptions(): void {
		$settings_review = $this->get_settings_review();

		wp_set_current_user( 0 );
		$this->assertFalse( $settings_review->can_save() );

		wp_set_current_user( $this->subscriber );
		$this->assertFalse( $settings_review->can_save() );

		wp_set_current_user( $this->admin );
		$this->assertTrue( $settings_review->can_save() );
	}

	public function testCompletingTheReviewSavesTheSettings(): void {
		wp_set_current_user( $this->admin );

		update_option( 'graphql_general_settings', [ 'graphql_endpoint' => 'graphql' ] );

		$response = $this->save(
			[
				'status'   => 'completed',
				'settings' => $this->core_settings(
					[
						'query_depth_max'   => 20,
						'tracing_user_role' => 'editor',
					]
				),
			]
		);

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$saved = get_option( 'graphql_general_settings' );
		$this->assertSame( 'on', $saved['query_depth_enabled'] );
		$this->assertSame( 20, $saved['query_depth_max'] );
		$this->assertSame( 'editor', $saved['tracing_user_role'] );

		// Settings outside the review are kept.
		$this->assertSame( 'graphql', $saved['graphql_endpoint'] );

		$state = SettingsReview::get_state();
		$this->assertSame( 'completed', $state['status'] );
		foreach ( self::CORE_KEYS as $key ) {
			$this->assertContains( $key, $state['reviewed'] );
		}

		$data = $response->get_data();
		$this->assertSame( 20, $data['values']->{'graphql_general_settings.query_depth_max'} );
		$this->assertSame( [], $data['unreviewedKeys'] );
	}

	public function testTheSaveResponseReportsTheNewValueOfSettingsWithADeclaredValue(): void {
		wp_set_current_user( $this->admin );

		// Like debug mode, this setting declares a `value` for the settings page, computed before the review saves.
		$settings_review = $this->get_settings_review(
			static function ( $registry ) {
				$registry->register_section( 'graphql_review_test_settings', [ 'title' => 'Review Test Settings' ] );
				$registry->register_field(
					'graphql_review_test_settings',
					[
						'name'         => 'declared_toggle',
						'label'        => 'Declared toggle',
						'type'         => 'checkbox',
						'default'      => 'off',
						'value'        => 'off',
						'settings_review' => true,
					]
				);
			}
		);

		$settings                                 = $this->core_settings();
		$settings['graphql_review_test_settings'] = [ 'declared_toggle' => 'on' ];

		$request = new \WP_REST_Request( 'POST', '/' . SettingsReview::REST_NAMESPACE . SettingsReview::REST_ROUTE );
		$request->set_param( 'status', 'completed' );
		$request->set_param( 'settings', $settings );

		$response = $settings_review->save( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 'on', $response->get_data()['values']->{'graphql_review_test_settings.declared_toggle'} );
	}

	public function testInvalidValuesAreRejectedAndNothingIsSaved(): void {
		wp_set_current_user( $this->admin );

		$settings = $this->core_settings(
			[
				'batch_limit'         => 'lots',
				'tracing_user_role'   => 'not-a-role',
				'query_depth_enabled' => 'yes',
			]
		);
		$settings['graphql_general_settings']['graphql_endpoint'] = 'somewhere-else';

		$response = $this->save(
			[
				'status'   => 'completed',
				'settings' => $settings,
			]
		);

		$this->assertWPError( $response );
		$this->assertSame( 400, $response->get_error_data()['status'] );

		$errors = $response->get_error_data()['errors'];
		$this->assertArrayHasKey( 'graphql_general_settings.batch_limit', $errors );
		$this->assertArrayHasKey( 'graphql_general_settings.tracing_user_role', $errors );
		$this->assertArrayHasKey( 'graphql_general_settings.query_depth_enabled', $errors );
		$this->assertArrayHasKey( 'graphql_general_settings.graphql_endpoint', $errors );

		$this->assertFalse( get_option( 'graphql_general_settings' ) );
		$this->assertSame( [], SettingsReview::get_state() );
	}

	public function testEverySettingMustBeSubmittedToComplete(): void {
		wp_set_current_user( $this->admin );

		$settings = $this->core_settings();
		unset( $settings['graphql_general_settings']['batch_limit'] );

		$response = $this->save(
			[
				'status'   => 'completed',
				'settings' => $settings,
			]
		);

		$this->assertWPError( $response );
		$this->assertArrayHasKey( 'graphql_general_settings.batch_limit', $response->get_error_data()['errors'] );
		$this->assertFalse( get_option( 'graphql_general_settings' ) );
	}

	public function testSkippingTheReviewChangesNoSettings(): void {
		wp_set_current_user( $this->admin );

		update_option( 'graphql_general_settings', [ 'query_depth_enabled' => 'off' ] );

		$response = $this->save(
			[
				'status'   => 'skipped',
				'settings' => $this->core_settings( [ 'query_depth_enabled' => 'on' ] ),
			]
		);

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( [ 'query_depth_enabled' => 'off' ], get_option( 'graphql_general_settings' ) );

		$state = SettingsReview::get_state();
		$this->assertSame( 'skipped', $state['status'] );
		$this->assertContains( 'graphql_general_settings.query_depth_enabled', $state['reviewed'] );
	}

	public function testLockedSettingsAreNotSaved(): void {
		wp_set_current_user( $this->admin );

		$settings_review = $this->get_settings_review(
			static function ( $registry ) {
				$registry->register_section( 'graphql_review_test_settings', [ 'title' => 'Review Test Settings' ] );
				$registry->register_field(
					'graphql_review_test_settings',
					[
						'name'         => 'locked_toggle',
						'label'        => 'Locked toggle',
						'type'         => 'checkbox',
						'default'      => 'off',
						'disabled'     => true,
						'settings_review' => true,
					]
				);
			}
		);

		$this->assertTrue( $settings_review->get_fields()['graphql_review_test_settings.locked_toggle']['locked'] );

		$settings                                 = $this->core_settings();
		$settings['graphql_review_test_settings'] = [ 'locked_toggle' => 'on' ];

		$request = new \WP_REST_Request( 'POST', '/' . SettingsReview::REST_NAMESPACE . SettingsReview::REST_ROUTE );
		$request->set_param( 'status', 'completed' );
		$request->set_param( 'settings', $settings );

		$response = $settings_review->save( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertFalse( get_option( 'graphql_review_test_settings' ) );
	}

	public function testSavedValuesRunThroughTheSettingSanitizeCallback(): void {
		wp_set_current_user( $this->admin );

		$settings_review = $this->get_settings_review(
			static function ( $registry ) {
				$registry->register_section( 'graphql_review_test_settings', [ 'title' => 'Review Test Settings' ] );
				$registry->register_field(
					'graphql_review_test_settings',
					[
						'name'              => 'shouting_text',
						'label'             => 'Shouting text',
						'type'              => 'text',
						'sanitize_callback' => 'strtoupper',
						'settings_review'      => true,
					]
				);
			}
		);

		$settings                                 = $this->core_settings();
		$settings['graphql_review_test_settings'] = [ 'shouting_text' => 'quiet' ];

		$request = new \WP_REST_Request( 'POST', '/' . SettingsReview::REST_NAMESPACE . SettingsReview::REST_ROUTE );
		$request->set_param( 'status', 'completed' );
		$request->set_param( 'settings', $settings );

		$settings_review->save( $request );

		$this->assertSame( [ 'shouting_text' => 'QUIET' ], get_option( 'graphql_review_test_settings' ) );
	}

	public function testNoticeIsRemovedWhenEverySettingIsReviewed(): void {
		wp_set_current_user( $this->admin );

		$settings_review = $this->get_settings_review();

		\WPGraphQL\Admin\AdminNotices::get_instance()->add_admin_notice( SettingsReview::NOTICE_SLUG, [ 'message' => 'Test' ] );

		$settings_review->maybe_remove_admin_notice();
		$this->assertArrayHasKey( SettingsReview::NOTICE_SLUG, get_graphql_admin_notices() );

		update_option(
			SettingsReview::STATE_OPTION,
			[
				'status'   => 'completed',
				'reviewed' => array_keys( $settings_review->get_fields() ),
			]
		);

		$settings_review->maybe_remove_admin_notice();
		$this->assertArrayNotHasKey( SettingsReview::NOTICE_SLUG, get_graphql_admin_notices() );
	}

	public function testMenuItemShowsOnlyWhileSettingsNeedReview(): void {
		global $submenu;

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$original_submenu = $submenu;
		wp_set_current_user( $this->admin );

		// Nothing reviewed yet: the review has a GraphQL menu item.
		$submenu = [];
		$settings_review  = $this->get_settings_review();
		$settings_review->register_admin_page();

		$this->assertTrue( $settings_review->shows_in_menu() );
		$this->assertContains( SettingsReview::PAGE_SLUG, wp_list_pluck( $submenu['graphiql-ide'] ?? [], 2 ) );

		// Every setting reviewed: the page is still registered, but not in the GraphQL menu.
		update_option(
			SettingsReview::STATE_OPTION,
			[
				'status'   => 'skipped',
				'reviewed' => array_keys( $settings_review->get_fields() ),
			]
		);

		$submenu = [];
		$settings_review  = $this->get_settings_review();
		$settings_review->register_admin_page();

		$this->assertFalse( $settings_review->shows_in_menu() );
		$this->assertNotContains( SettingsReview::PAGE_SLUG, wp_list_pluck( $submenu['graphiql-ide'] ?? [], 2 ) );
		$this->assertNotEmpty( get_plugin_page_hookname( SettingsReview::PAGE_SLUG, '' ) );

		$submenu = $original_submenu;
	}
}
