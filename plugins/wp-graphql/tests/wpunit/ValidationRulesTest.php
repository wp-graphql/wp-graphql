<?php

use WPGraphQL\Server\ValidationRules\DisableIntrospection;
use WPGraphQL\Server\ValidationRules\QueryDepth;
use WPGraphQL\Server\ValidationRules\RequireAuthentication;

class ValidationRulesTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * Saved original REQUEST_URI server superglobal.
	 *
	 * @var string|null
	 */
	protected $orig_request_uri;

	/**
	 * Saved original HTTP_HOST server superglobal.
	 *
	 * @var string|null
	 */
	protected $orig_http_host;

	/**
	 * Set up before each test to capture original server globals.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->orig_request_uri = $_SERVER['REQUEST_URI'] ?? null;
		$this->orig_http_host   = $_SERVER['HTTP_HOST'] ?? null;
	}

	/**
	 * Tear down after each test to restore isolated state.
	 */
	public function tearDown(): void {
		if ( null !== $this->orig_request_uri ) {
			$_SERVER['REQUEST_URI'] = $this->orig_request_uri;
		} else {
			unset( $_SERVER['REQUEST_URI'] );
		}

		if ( null !== $this->orig_http_host ) {
			$_SERVER['HTTP_HOST'] = $this->orig_http_host;
		} else {
			unset( $_SERVER['HTTP_HOST'] );
		}

		remove_all_filters( 'graphql_debug_enabled' );
		remove_all_filters( 'graphql_pre_restrict_endpoint' );
		remove_all_filters( 'graphql_require_authentication_allowed_fields' );
		delete_option( 'graphql_general_settings' );
		wp_set_current_user( 0 );
		$this->clearSchema();
		parent::tearDown();
	}

	/**
	 * Helper method to invoke protected methods via Reflection.
	 *
	 * @param object  $target The target object instance.
	 * @param string  $method The method name to invoke.
	 * @param mixed[] $args   Arguments to pass to the method.
	 *
	 * @return mixed
	 */
	protected function invokeProtectedMethod( $target, string $method, array $args = [] ) {
		$reflection        = new \ReflectionClass( get_class( $target ) );
		$method_reflection = $reflection->getMethod( $method );
		$method_reflection->setAccessible( true );
		return $method_reflection->invokeArgs( $target, $args );
	}

	/**
	 * Tests DisableIntrospection should_be_enabled returns true for unauthenticated public requests when debug is off.
	 */
	public function testDisableIntrospectionEnabledForPublicGuestWhenDebugIsOff() {
		wp_set_current_user( 0 );

		$settings                                 = get_option( 'graphql_general_settings', [] );
		$settings['public_introspection_enabled'] = 'off';
		update_option( 'graphql_general_settings', $settings );

		add_filter( 'graphql_debug_enabled', '__return_false' );

		$rule = new DisableIntrospection();

		$this->assertTrue( $rule->should_be_enabled() );
		$this->assertTrue( $this->invokeProtectedMethod( $rule, 'isEnabled' ) );
	}

	/**
	 * Tests DisableIntrospection should_be_enabled returns false when an authenticated user is logged in.
	 */
	public function testDisableIntrospectionDisabledForAuthenticatedUser() {
		$user_id = $this->factory()->user->create(
			[
				'role' => 'subscriber',
			]
		);
		wp_set_current_user( $user_id );

		$settings                                 = get_option( 'graphql_general_settings', [] );
		$settings['public_introspection_enabled'] = 'off';
		update_option( 'graphql_general_settings', $settings );

		$rule = new DisableIntrospection();

		$this->assertFalse( $rule->should_be_enabled() );
		$this->assertFalse( $this->invokeProtectedMethod( $rule, 'isEnabled' ) );
	}

	/**
	 * Tests DisableIntrospection should_be_enabled returns false when public_introspection_enabled setting is 'on'.
	 */
	public function testDisableIntrospectionDisabledWhenPublicSettingIsOn() {
		wp_set_current_user( 0 );

		$settings                                 = get_option( 'graphql_general_settings', [] );
		$settings['public_introspection_enabled'] = 'on';
		update_option( 'graphql_general_settings', $settings );

		$rule = new DisableIntrospection();

		$this->assertFalse( $rule->should_be_enabled() );
		$this->assertFalse( $this->invokeProtectedMethod( $rule, 'isEnabled' ) );
	}

	/**
	 * Tests DisableIntrospection should_be_enabled returns false when GraphQL debug mode is active.
	 */
	public function testDisableIntrospectionDisabledWhenDebugModeActive() {
		wp_set_current_user( 0 );

		$settings                                 = get_option( 'graphql_general_settings', [] );
		$settings['public_introspection_enabled'] = 'off';
		update_option( 'graphql_general_settings', $settings );

		add_filter( 'graphql_debug_enabled', '__return_true' );

		$rule = new DisableIntrospection();

		$this->assertFalse( $rule->should_be_enabled() );
		$this->assertFalse( $this->invokeProtectedMethod( $rule, 'isEnabled' ) );
	}

	/**
	 * Tests DisableIntrospection introspectionDisabledMessage returns the expected localized message.
	 */
	public function testDisableIntrospectionDisabledMessage() {
		$message = DisableIntrospection::introspectionDisabledMessage();

		$this->assertIsString( $message );
		$this->assertStringContainsString( '__schema or __type', $message );
		$this->assertStringContainsString( 'introspection is not allowed for public requests by default', $message );
	}

	/**
	 * Tests QueryDepth default constructor sets maxQueryDepth to default 10.
	 */
	public function testQueryDepthDefaultConfiguration() {
		delete_option( 'graphql_general_settings' );

		$rule = new QueryDepth();

		$this->assertSame( 10, $rule->getMaxQueryDepth() );
	}

	/**
	 * Tests QueryDepth constructor honors custom query_depth_max setting.
	 */
	public function testQueryDepthCustomSettingFromDatabase() {
		$settings                    = get_option( 'graphql_general_settings', [] );
		$settings['query_depth_max'] = 7;
		update_option( 'graphql_general_settings', $settings );

		$rule = new QueryDepth();

		$this->assertSame( 7, $rule->getMaxQueryDepth() );
	}

	/**
	 * Tests QueryDepth setMaxQueryDepth updates the max depth value.
	 */
	public function testQueryDepthSetMaxQueryDepth() {
		$rule = new QueryDepth();
		$rule->setMaxQueryDepth( 20 );

		$this->assertSame( 20, $rule->getMaxQueryDepth() );

		$rule->setMaxQueryDepth( 0 );
		$this->assertSame( 0, $rule->getMaxQueryDepth() );
	}

	/**
	 * Tests QueryDepth setMaxQueryDepth throws InvalidArgumentException for negative depth values.
	 */
	public function testQueryDepthThrowsExceptionForNegativeDepth() {
		$rule = new QueryDepth();

		$this->expectException( \InvalidArgumentException::class );
		$rule->setMaxQueryDepth( -1 );
	}

	/**
	 * Tests QueryDepth errorMessage formats max allowed depth and actual count into a descriptive message.
	 */
	public function testQueryDepthErrorMessage() {
		$rule    = new QueryDepth();
		$message = $rule->errorMessage( 5, 8 );

		$this->assertSame( 'The server administrator has limited the max query depth to 5, but the requested query has 8 levels.', $message );
	}

	/**
	 * Tests QueryDepth isEnabled conditions based on query_depth_enabled setting and maxQueryDepth value.
	 */
	public function testQueryDepthIsEnabledConditions() {
		$rule = new QueryDepth();

		// Case A: query_depth_enabled is off.
		$settings                        = get_option( 'graphql_general_settings', [] );
		$settings['query_depth_enabled'] = 'off';
		update_option( 'graphql_general_settings', $settings );

		$this->assertFalse( $this->invokeProtectedMethod( $rule, 'isEnabled' ) );

		// Case B: query_depth_enabled is on and depth >= 1.
		$settings['query_depth_enabled'] = 'on';
		update_option( 'graphql_general_settings', $settings );
		$rule->setMaxQueryDepth( 10 );

		$this->assertTrue( $this->invokeProtectedMethod( $rule, 'isEnabled' ) );

		// Case C: query_depth_enabled is on but depth is 0.
		$rule->setMaxQueryDepth( 0 );

		$this->assertFalse( $this->invokeProtectedMethod( $rule, 'isEnabled' ) );
	}

	/**
	 * Tests RequireAuthentication isEnabled can be overridden via graphql_pre_restrict_endpoint filter.
	 */
	public function testRequireAuthenticationPreRestrictFilterOverride() {
		$rule = new RequireAuthentication();

		add_filter( 'graphql_pre_restrict_endpoint', '__return_true' );
		$this->assertTrue( $this->invokeProtectedMethod( $rule, 'isEnabled' ) );

		remove_all_filters( 'graphql_pre_restrict_endpoint' );

		add_filter( 'graphql_pre_restrict_endpoint', '__return_false' );
		$this->assertFalse( $this->invokeProtectedMethod( $rule, 'isEnabled' ) );
	}

	/**
	 * Tests RequireAuthentication isEnabled returns false when request is not a GraphQL HTTP request.
	 */
	public function testRequireAuthenticationDisabledWhenNotHttpRequest() {
		$_SERVER['REQUEST_URI'] = '';
		$_SERVER['HTTP_HOST']   = 'localhost';

		$settings = get_option( 'graphql_general_settings', [] );
		$settings['restrict_endpoint_to_logged_in_users'] = 'on';
		update_option( 'graphql_general_settings', $settings );

		$rule = new RequireAuthentication();

		$this->assertFalse( $this->invokeProtectedMethod( $rule, 'isEnabled' ) );
	}

	/**
	 * Tests RequireAuthentication isEnabled returns true only for unauthenticated guests when setting is 'on'.
	 */
	public function testRequireAuthenticationSettingsAndUserConditions() {
		$_SERVER['HTTP_HOST']   = 'localhost';
		$_SERVER['REQUEST_URI'] = '/graphql';

		$rule = new RequireAuthentication();

		// Case A: Setting is 'off' or empty.
		$settings = get_option( 'graphql_general_settings', [] );
		$settings['restrict_endpoint_to_logged_in_users'] = 'off';
		update_option( 'graphql_general_settings', $settings );

		$this->assertFalse( $this->invokeProtectedMethod( $rule, 'isEnabled' ) );

		// Case B: Setting is 'on' and user is logged in.
		$settings['restrict_endpoint_to_logged_in_users'] = 'on';
		update_option( 'graphql_general_settings', $settings );

		$user_id = $this->factory()->user->create(
			[
				'role' => 'subscriber',
			]
		);
		wp_set_current_user( $user_id );

		$this->assertFalse( $this->invokeProtectedMethod( $rule, 'isEnabled' ) );

		// Case C: Setting is 'on' and user is guest.
		wp_set_current_user( 0 );

		$this->assertTrue( $this->invokeProtectedMethod( $rule, 'isEnabled' ) );
	}

	/**
	 * Tests RequireAuthentication blocks unauthenticated access to root query fields when enabled.
	 */
	public function testRequireAuthenticationBlocksUnauthenticatedRootFields() {
		wp_set_current_user( 0 );
		add_filter( 'graphql_pre_restrict_endpoint', '__return_true' );

		$query  = 'query TestRestrictedQuery { posts { nodes { id } } }';
		$result = graphql( [ 'query' => $query ] );

		$this->assertNotEmpty( $result['errors'] );
		$this->assertStringContainsString( 'cannot be accessed without authentication', $result['errors'][0]['message'] );
		$this->assertStringContainsString( 'RootQuery.posts', $result['errors'][0]['message'] );
	}

	/**
	 * Tests RequireAuthentication permits access to allowed root fields filtered via graphql_require_authentication_allowed_fields.
	 */
	public function testRequireAuthenticationAllowsWhitelistedRootFields() {
		wp_set_current_user( 0 );
		add_filter( 'graphql_pre_restrict_endpoint', '__return_true' );

		add_filter(
			'graphql_require_authentication_allowed_fields',
			static function ( $allowed_fields ) {
				$allowed_fields[] = 'generalSettings';
				return $allowed_fields;
			}
		);

		$query  = 'query TestWhitelistedQuery { generalSettings { title } }';
		$result = graphql( [ 'query' => $query ] );

		$this->assertArrayNotHasKey( 'errors', $result );
		$this->assertArrayHasKey( 'data', $result );
		$this->assertArrayHasKey( 'generalSettings', $result['data'] );
	}
}
