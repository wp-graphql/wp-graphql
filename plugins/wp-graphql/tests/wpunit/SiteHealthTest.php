<?php

use WPGraphQL\Admin\SiteHealth\SiteHealth;

/**
 * Tests for the WPGraphQL recommendations shown in Tools > Site Health.
 */
class SiteHealthTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * The Site Health test identifiers WPGraphQL registers.
	 */
	private const TEST_IDS = [
		'wpgraphql_public_introspection',
		'wpgraphql_debug_mode',
		'wpgraphql_query_depth',
		'wpgraphql_debug_output',
	];

	public function setUp(): void {
		parent::setUp();

		delete_option( 'graphql_general_settings' );

		// The test config defines GRAPHQL_DEBUG as true, so debug mode starts off with the filter.
		$this->set_debug_mode( false );

		if ( ! class_exists( 'WP_Site_Health' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
		}
	}

	public function tearDown(): void {
		delete_option( 'graphql_general_settings' );
		remove_all_filters( 'graphql_debug_enabled' );
		remove_all_filters( 'graphql_pre_restrict_endpoint' );

		parent::tearDown();
	}

	/**
	 * Turns GraphQL debug mode on or off.
	 *
	 * @param bool $enabled Whether debug mode is on.
	 */
	private function set_debug_mode( bool $enabled ): void {
		remove_all_filters( 'graphql_debug_enabled' );
		add_filter( 'graphql_debug_enabled', $enabled ? '__return_true' : '__return_false' );
	}

	/**
	 * Returns Site Health tests that see the given GRAPHQL_DEBUG constant value, since the test
	 * config always defines it.
	 *
	 * @param bool|null $value The constant's value, or null for not defined.
	 */
	private function site_health_with_debug_constant( ?bool $value ): SiteHealth {
		return new class( $value ) extends SiteHealth {
			/**
			 * @var bool|null
			 */
			private $debug_constant;

			public function __construct( ?bool $debug_constant ) {
				$this->debug_constant = $debug_constant;
			}

			protected function get_debug_constant(): ?bool {
				return $this->debug_constant;
			}
		};
	}

	/**
	 * Saves WPGraphQL settings, keeping the defaults for any not passed.
	 *
	 * @param array<string,mixed> $settings The settings to save.
	 */
	private function save_settings( array $settings ): void {
		update_option( 'graphql_general_settings', $settings );
	}

	/**
	 * Runs one of WPGraphQL's Site Health tests the way Site Health does: through the callback
	 * registered with the site_status_tests filter.
	 *
	 * @param string $id The test identifier.
	 *
	 * @return array<string,mixed> The test result.
	 */
	private function run_test( string $id ): array {
		$tests = WP_Site_Health::get_tests();

		$this->assertArrayHasKey( $id, $tests['direct'] );

		return call_user_func( $tests['direct'][ $id ]['test'] );
	}

	public function testRegistersTestsOnProductionSites(): void {
		$this->assertSame( 'production', wp_get_environment_type() );

		$tests = WP_Site_Health::get_tests();

		foreach ( self::TEST_IDS as $id ) {
			$this->assertArrayHasKey( $id, $tests['direct'] );
			$this->assertNotEmpty( $tests['direct'][ $id ]['label'] );
		}
	}

	public function testInitRegistersTheTestsWithSiteHealth(): void {
		remove_all_filters( 'site_status_tests' );

		( new SiteHealth() )->init();

		$tests = WP_Site_Health::get_tests();

		foreach ( self::TEST_IDS as $id ) {
			$this->assertArrayHasKey( $id, $tests['direct'] );
		}
	}

	public function testAdminRegistersTheTestsWhenTheAdminPagesAreOn(): void {
		remove_all_filters( 'site_status_tests' );

		( new \WPGraphQL\Admin\Admin() )->init();

		$tests = WP_Site_Health::get_tests();

		foreach ( self::TEST_IDS as $id ) {
			$this->assertArrayHasKey( $id, $tests['direct'] );
		}
	}

	public function testDoesNotRegisterTestsWhenTheAdminPagesAreOff(): void {
		remove_all_filters( 'site_status_tests' );
		add_filter( 'graphql_show_admin', '__return_false' );

		( new \WPGraphQL\Admin\Admin() )->init();

		remove_filter( 'graphql_show_admin', '__return_false' );

		$tests = WP_Site_Health::get_tests();

		foreach ( self::TEST_IDS as $id ) {
			$this->assertArrayNotHasKey( $id, $tests['direct'] );
		}
	}

	public function testDoesNotRegisterTestsOutsideProduction(): void {
		$site_health = new class() extends SiteHealth {
			protected function get_environment_type(): string {
				return 'staging';
			}
		};

		$tests = $site_health->register_tests( [ 'direct' => [], 'async' => [] ] );

		$this->assertSame( [ 'direct' => [], 'async' => [] ], $tests );
	}

	public function testLeavesUnexpectedFilterValuesAlone(): void {
		$this->assertNull( ( new SiteHealth() )->register_tests( null ) );
	}

	public function testResultsHaveTheShapeSiteHealthExpects(): void {
		foreach ( self::TEST_IDS as $id ) {
			$result = $this->run_test( $id );

			$this->assertSame( $id, $result['test'] );
			$this->assertContains( $result['status'], [ 'good', 'recommended' ] );
			$this->assertNotEmpty( $result['label'] );
			$this->assertNotEmpty( $result['badge']['label'] );
			$this->assertSame( 'blue', $result['badge']['color'] );
			$this->assertNotEmpty( $result['description'] );
			$this->assertArrayHasKey( 'actions', $result );
		}
	}

	public function testNeverReportsCritical(): void {
		$this->set_debug_mode( true );
		$this->save_settings(
			[
				'public_introspection_enabled' => 'on',
				'query_depth_enabled'          => 'off',
				'tracing_enabled'              => 'on',
				'tracing_user_role'            => 'any',
				'query_logs_enabled'           => 'on',
				'query_log_user_role'          => 'any',
			]
		);

		foreach ( self::TEST_IDS as $id ) {
			$this->assertSame( 'recommended', $this->run_test( $id )['status'], $id );
		}
	}

	public function testPublicIntrospectionIsGoodByDefault(): void {
		$this->assertSame( 'good', $this->run_test( 'wpgraphql_public_introspection' )['status'] );
	}

	public function testPublicIntrospectionIsRecommendedWhenOn(): void {
		$this->save_settings( [ 'public_introspection_enabled' => 'on' ] );

		$result = $this->run_test( 'wpgraphql_public_introspection' );

		$this->assertSame( 'recommended', $result['status'] );
		// Explains how decoupled clients can get the schema without public introspection.
		$this->assertStringContainsString( 'application password', $result['description'] );
		$this->assertStringContainsString( 'wp graphql generate-static-schema', $result['description'] );
		$this->assertStringContainsString( 'page=graphql-settings', $result['actions'] );
	}

	public function testPublicIntrospectionIsRecommendedWhenDebugModeTurnsItOn(): void {
		$this->set_debug_mode( true );

		$result = $this->run_test( 'wpgraphql_public_introspection' );

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'The introspection setting is off, but debug mode is on', $result['description'] );
		$this->assertStringContainsString( 'Review debug mode', $result['actions'] );
	}

	public function testPublicIntrospectionSaysBothNeedTurningOffWhenDebugModeAndTheSettingAreOn(): void {
		$this->set_debug_mode( true );
		$this->save_settings( [ 'public_introspection_enabled' => 'on' ] );

		$result = $this->run_test( 'wpgraphql_public_introspection' );

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'so both have to be off', $result['description'] );
	}

	public function testPublicIntrospectionLinksToTheDocsOnGettingTheSchema(): void {
		$this->save_settings( [ 'public_introspection_enabled' => 'on' ] );

		$result = $this->run_test( 'wpgraphql_public_introspection' );

		$this->assertStringContainsString( 'docs/security#getting-the-schema-without-public-introspection', $result['actions'] );
	}

	public function testPublicIntrospectionIsGoodWhenOnlyLoggedInUsersCanUseTheApi(): void {
		$this->save_settings(
			[
				'public_introspection_enabled'         => 'on',
				'restrict_endpoint_to_logged_in_users' => 'on',
			]
		);

		$this->assertSame( 'good', $this->run_test( 'wpgraphql_public_introspection' )['status'] );
	}

	public function testPublicIntrospectionFollowsTheRestrictEndpointFilter(): void {
		$this->save_settings(
			[
				'public_introspection_enabled'         => 'on',
				'restrict_endpoint_to_logged_in_users' => 'off',
			]
		);
		add_filter( 'graphql_pre_restrict_endpoint', '__return_true' );

		$this->assertSame( 'good', $this->run_test( 'wpgraphql_public_introspection' )['status'] );

		$this->save_settings(
			[
				'public_introspection_enabled'         => 'on',
				'restrict_endpoint_to_logged_in_users' => 'on',
			]
		);
		remove_all_filters( 'graphql_pre_restrict_endpoint' );
		add_filter( 'graphql_pre_restrict_endpoint', '__return_false' );

		$this->assertSame( 'recommended', $this->run_test( 'wpgraphql_public_introspection' )['status'] );
	}

	public function testDebugModeIsGoodByDefault(): void {
		$this->assertSame( 'good', $this->run_test( 'wpgraphql_debug_mode' )['status'] );
	}

	public function testDebugModeIsRecommendedWhenOn(): void {
		$this->set_debug_mode( true );

		$result = $this->run_test( 'wpgraphql_debug_mode' );

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'page=graphql-settings', $result['actions'] );
		// The test config turns debug mode on with the GRAPHQL_DEBUG constant.
		$this->assertStringContainsString( 'GRAPHQL_DEBUG', $result['description'] );
	}

	public function testDebugModeSaysWhenTheFilterTurnedItOn(): void {
		$this->set_debug_mode( true );

		// No constant, and the setting is off, so the filter turned it on.
		$result = $this->site_health_with_debug_constant( null )->test_debug_mode();
		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'graphql_debug_enabled', $result['description'] );
		$this->assertStringNotContainsString( 'GRAPHQL_DEBUG', $result['description'] );

		// The constant is false, so only the filter can have turned it on.
		$result = $this->site_health_with_debug_constant( false )->test_debug_mode();
		$this->assertStringContainsString( 'graphql_debug_enabled', $result['description'] );
	}

	public function testDebugModeDoesNotMentionCodeWhenTheSettingTurnedItOn(): void {
		$this->set_debug_mode( true );
		$this->save_settings( [ 'debug_mode_enabled' => 'on' ] );

		$result = $this->site_health_with_debug_constant( null )->test_debug_mode();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringNotContainsString( 'graphql_debug_enabled', $result['description'] );
		$this->assertStringNotContainsString( 'GRAPHQL_DEBUG', $result['description'] );
		$this->assertStringContainsString( 'Review debug mode', $result['actions'] );
	}

	public function testQueryDepthIsRecommendedByDefault(): void {
		$result = $this->run_test( 'wpgraphql_query_depth' );

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'page=graphql-settings', $result['actions'] );
		$this->assertStringContainsString( 'graphql_query_depth_max', $result['description'] );
	}

	public function testQueryDepthIsGoodWhenLimited(): void {
		$this->save_settings( [ 'query_depth_enabled' => 'on' ] );

		$this->assertSame( 'good', $this->run_test( 'wpgraphql_query_depth' )['status'] );
	}

	public function testDebugOutputIsGoodByDefault(): void {
		$this->assertSame( 'good', $this->run_test( 'wpgraphql_debug_output' )['status'] );
	}

	public function testDebugOutputIsGoodWhenLimitedToARole(): void {
		$this->save_settings(
			[
				'tracing_enabled'     => 'on',
				'tracing_user_role'   => 'administrator',
				'query_logs_enabled'  => 'on',
				'query_log_user_role' => 'administrator',
			]
		);

		$this->assertSame( 'good', $this->run_test( 'wpgraphql_debug_output' )['status'] );
	}

	public function testDebugOutputIsGoodWhenAnyUserIsChosenButTheFeatureIsOff(): void {
		$this->save_settings(
			[
				'tracing_enabled'     => 'off',
				'tracing_user_role'   => 'any',
				'query_logs_enabled'  => 'off',
				'query_log_user_role' => 'any',
			]
		);

		$this->assertSame( 'good', $this->run_test( 'wpgraphql_debug_output' )['status'] );
	}

	public function testDebugOutputIsRecommendedWhenTracingIsShownToAnyUser(): void {
		$this->save_settings(
			[
				'tracing_enabled'   => 'on',
				'tracing_user_role' => 'any',
			]
		);

		$result = $this->run_test( 'wpgraphql_debug_output' );

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'Tracing', $result['description'] );
		$this->assertStringNotContainsString( 'Query logs', $result['description'] );
	}

	public function testDebugOutputIsRecommendedWhenQueryLogsAreShownToAnyUser(): void {
		$this->save_settings(
			[
				'query_logs_enabled'  => 'on',
				'query_log_user_role' => 'any',
			]
		);

		$result = $this->run_test( 'wpgraphql_debug_output' );

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'Query logs', $result['description'] );
		$this->assertStringNotContainsString( 'Tracing', $result['description'] );
	}
}
