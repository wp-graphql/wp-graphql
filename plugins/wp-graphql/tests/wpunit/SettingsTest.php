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
}
