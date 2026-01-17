<?php

/**
 * Tests for the Settings class
 */
class SettingsTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * @var \WPGraphQL\Admin\Settings\Settings
	 */
	private $settings;

	public function setUp(): void {
		parent::setUp();

		$this->settings = new \WPGraphQL\Admin\Settings\Settings();
		$this->settings->init();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Test that graphql_endpoint sanitization handles empty values
	 *
	 * When an empty value is submitted, it should return the default 'graphql'
	 * and add a settings error.
	 */
	public function testGraphqlEndpointSanitizationHandlesEmptyValues() {
		// Initialize settings to register the fields
		$this->settings->register_settings();
		$this->settings->settings_api->admin_init();

		// Test with empty string
		$result = $this->settings->settings_api->sanitize_options( [
			'graphql_endpoint' => '',
		] );

		$this->assertArrayHasKey( 'graphql_endpoint', $result );
		$this->assertSame( 'graphql', $result['graphql_endpoint'] );

		// Test with whitespace only (sanitize_text_field trims whitespace)
		$result = $this->settings->settings_api->sanitize_options( [
			'graphql_endpoint' => '   ',
		] );

		$this->assertArrayHasKey( 'graphql_endpoint', $result );
		$this->assertSame( 'graphql', $result['graphql_endpoint'] );
	}

	/**
	 * Test that graphql_endpoint sanitization preserves valid values
	 */
	public function testGraphqlEndpointSanitizationPreservesValidValues() {
		// Initialize settings
		$this->settings->register_settings();
		$this->settings->settings_api->admin_init();

		// Test with a valid custom endpoint
		$result = $this->settings->settings_api->sanitize_options( [
			'graphql_endpoint' => 'api',
		] );

		$this->assertArrayHasKey( 'graphql_endpoint', $result );
		$this->assertSame( 'api', $result['graphql_endpoint'] );

		// Test with another valid value
		$result = $this->settings->settings_api->sanitize_options( [
			'graphql_endpoint' => 'my-graphql-endpoint',
		] );

		$this->assertArrayHasKey( 'graphql_endpoint', $result );
		$this->assertSame( 'my-graphql-endpoint', $result['graphql_endpoint'] );
	}

	/**
	 * Test that graphql_endpoint sanitization strips HTML tags
	 *
	 * This ensures malicious input like script tags are removed.
	 */
	public function testGraphqlEndpointSanitizationStripsHtml() {
		// Initialize settings
		$this->settings->register_settings();
		$this->settings->settings_api->admin_init();

		// Test with HTML tags
		$result = $this->settings->settings_api->sanitize_options( [
			'graphql_endpoint' => '<script>alert("xss")</script>graphql',
		] );

		$this->assertArrayHasKey( 'graphql_endpoint', $result );
		$this->assertSame( 'graphql', $result['graphql_endpoint'] );

		// Test with nested tags
		$result = $this->settings->settings_api->sanitize_options( [
			'graphql_endpoint' => '<div><a href="evil">api</a></div>',
		] );

		$this->assertArrayHasKey( 'graphql_endpoint', $result );
		$this->assertSame( 'api', $result['graphql_endpoint'] );
	}

	/**
	 * Test that graphql_endpoint sanitization handles slashed input
	 *
	 * WordPress may add slashes to input values, which should be removed.
	 */
	public function testGraphqlEndpointSanitizationHandlesSlashedInput() {
		// Initialize settings
		$this->settings->register_settings();
		$this->settings->settings_api->admin_init();

		// Test with slashed input (WordPress adds slashes to POST data)
		$result = $this->settings->settings_api->sanitize_options( [
			'graphql_endpoint' => addslashes( "graphql's-endpoint" ),
		] );

		$this->assertArrayHasKey( 'graphql_endpoint', $result );
		$this->assertSame( "graphql's-endpoint", $result['graphql_endpoint'] );
	}

	/**
	 * Test that graphql_endpoint sanitization trims whitespace
	 */
	public function testGraphqlEndpointSanitizationTrimsWhitespace() {
		// Initialize settings
		$this->settings->register_settings();
		$this->settings->settings_api->admin_init();

		// Test with leading/trailing whitespace
		$result = $this->settings->settings_api->sanitize_options( [
			'graphql_endpoint' => '  api  ',
		] );

		$this->assertArrayHasKey( 'graphql_endpoint', $result );
		$this->assertSame( 'api', $result['graphql_endpoint'] );
	}

	/**
	 * Test that batch_limit sanitization converts to positive integer
	 */
	public function testBatchLimitSanitizationConvertsToPositiveInteger() {
		// Initialize settings
		$this->settings->register_settings();
		$this->settings->settings_api->admin_init();

		// Test with valid integer
		$result = $this->settings->settings_api->sanitize_options( [
			'batch_limit' => 15,
		] );

		$this->assertArrayHasKey( 'batch_limit', $result );
		$this->assertSame( 15, $result['batch_limit'] );

		// Test with string number
		$result = $this->settings->settings_api->sanitize_options( [
			'batch_limit' => '25',
		] );

		$this->assertArrayHasKey( 'batch_limit', $result );
		$this->assertSame( 25, $result['batch_limit'] );

		// Test with negative number (should return absolute value)
		$result = $this->settings->settings_api->sanitize_options( [
			'batch_limit' => -10,
		] );

		$this->assertArrayHasKey( 'batch_limit', $result );
		$this->assertSame( 10, $result['batch_limit'] );

		// Test with zero (should return default 10)
		$result = $this->settings->settings_api->sanitize_options( [
			'batch_limit' => 0,
		] );

		$this->assertArrayHasKey( 'batch_limit', $result );
		$this->assertSame( 10, $result['batch_limit'] );

		// Test with non-numeric string (should return default 10)
		$result = $this->settings->settings_api->sanitize_options( [
			'batch_limit' => 'abc',
		] );

		$this->assertArrayHasKey( 'batch_limit', $result );
		$this->assertSame( 10, $result['batch_limit'] );
	}

	/**
	 * Test that tracing_user_role sanitization validates against actual roles
	 */
	public function testTracingUserRoleSanitizationValidatesRole() {
		// Initialize settings
		$this->settings->register_settings();
		$this->settings->settings_api->admin_init();

		// Test with valid role
		$result = $this->settings->settings_api->sanitize_options( [
			'tracing_user_role' => 'administrator',
		] );

		$this->assertArrayHasKey( 'tracing_user_role', $result );
		$this->assertSame( 'administrator', $result['tracing_user_role'] );

		// Test with another valid role
		$result = $this->settings->settings_api->sanitize_options( [
			'tracing_user_role' => 'editor',
		] );

		$this->assertArrayHasKey( 'tracing_user_role', $result );
		$this->assertSame( 'editor', $result['tracing_user_role'] );

		// Test with 'any' (special valid value)
		$result = $this->settings->settings_api->sanitize_options( [
			'tracing_user_role' => 'any',
		] );

		$this->assertArrayHasKey( 'tracing_user_role', $result );
		$this->assertSame( 'any', $result['tracing_user_role'] );

		// Test with invalid role (should return default 'administrator')
		$result = $this->settings->settings_api->sanitize_options( [
			'tracing_user_role' => 'invalid_role',
		] );

		$this->assertArrayHasKey( 'tracing_user_role', $result );
		$this->assertSame( 'administrator', $result['tracing_user_role'] );

		// Test with XSS attempt (should return default 'administrator')
		$result = $this->settings->settings_api->sanitize_options( [
			'tracing_user_role' => '<script>alert("xss")</script>',
		] );

		$this->assertArrayHasKey( 'tracing_user_role', $result );
		$this->assertSame( 'administrator', $result['tracing_user_role'] );
	}

	/**
	 * Test that query_log_user_role sanitization validates against actual roles
	 */
	public function testQueryLogUserRoleSanitizationValidatesRole() {
		// Initialize settings
		$this->settings->register_settings();
		$this->settings->settings_api->admin_init();

		// Test with valid role
		$result = $this->settings->settings_api->sanitize_options( [
			'query_log_user_role' => 'administrator',
		] );

		$this->assertArrayHasKey( 'query_log_user_role', $result );
		$this->assertSame( 'administrator', $result['query_log_user_role'] );

		// Test with another valid role
		$result = $this->settings->settings_api->sanitize_options( [
			'query_log_user_role' => 'editor',
		] );

		$this->assertArrayHasKey( 'query_log_user_role', $result );
		$this->assertSame( 'editor', $result['query_log_user_role'] );

		// Test with 'any' (special valid value)
		$result = $this->settings->settings_api->sanitize_options( [
			'query_log_user_role' => 'any',
		] );

		$this->assertArrayHasKey( 'query_log_user_role', $result );
		$this->assertSame( 'any', $result['query_log_user_role'] );

		// Test with invalid role (should return default 'administrator')
		$result = $this->settings->settings_api->sanitize_options( [
			'query_log_user_role' => 'hacker_role',
		] );

		$this->assertArrayHasKey( 'query_log_user_role', $result );
		$this->assertSame( 'administrator', $result['query_log_user_role'] );
	}
}
