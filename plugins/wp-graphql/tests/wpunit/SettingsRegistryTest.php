<?php

/**
 * Tests for SettingsRegistry class
 */
class SettingsRegistryTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * @var \WPGraphQL\Admin\Settings\SettingsRegistry
	 */
	private $registry;

	public function setUp(): void {
		parent::setUp();

		$this->registry = new \WPGraphQL\Admin\Settings\SettingsRegistry();
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Test that sanitize_options() handles non-array values gracefully
	 * 
	 * This test ensures that when WordPress passes non-array values (like empty strings)
	 * via the sanitize_option filter, the method returns them unchanged instead of
	 * throwing a TypeError.
	 */
	public function testSanitizeOptionsHandlesNonArrayValues() {
		// Test with empty string (the scenario from issue #3439)
		$result = $this->registry->sanitize_options( '' );
		$this->assertSame( '', $result );

		// Test with a non-empty string
		$result = $this->registry->sanitize_options( 'some_string_value' );
		$this->assertSame( 'some_string_value', $result );

		// Test with null
		$result = $this->registry->sanitize_options( null );
		$this->assertNull( $result );

		// Test with integer
		$result = $this->registry->sanitize_options( 123 );
		$this->assertSame( 123, $result );

		// Test with boolean
		$result = $this->registry->sanitize_options( true );
		$this->assertTrue( $result );

		$result = $this->registry->sanitize_options( false );
		$this->assertFalse( $result );
	}

	/**
	 * Test that sanitize_options() processes array values correctly
	 */
	public function testSanitizeOptionsProcessesArrayValues() {
		// Register a test section and field with a sanitize callback
		$this->registry->register_section(
			'test_section',
			[
				'title' => 'Test Section',
			]
		);

		$this->registry->register_field(
			'test_section',
			[
				'name'              => 'test_field',
				'label'             => 'Test Field',
				'type'              => 'text',
				'sanitize_callback' => function( $value ) {
					return strtoupper( $value );
				},
			]
		);

		// Initialize the registry to register settings
		$this->registry->admin_init();

		// Test with an array containing a field that has a sanitize callback
		$input = [
			'test_field' => 'hello world',
		];

		$result = $this->registry->sanitize_options( $input );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'test_field', $result );
		$this->assertSame( 'HELLO WORLD', $result['test_field'] );
	}

	/**
	 * Test that sanitize_options() handles empty arrays
	 */
	public function testSanitizeOptionsHandlesEmptyArrays() {
		$result = $this->registry->sanitize_options( [] );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test that sanitize_options() handles arrays without registered fields
	 */
	public function testSanitizeOptionsHandlesArraysWithoutRegisteredFields() {
		// Test with an array that doesn't have any registered fields
		$input = [
			'unregistered_field' => 'some_value',
		];

		$result = $this->registry->sanitize_options( $input );

		// Should return the array unchanged since there's no sanitize callback
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'unregistered_field', $result );
		$this->assertSame( 'some_value', $result['unregistered_field'] );
	}

	/**
	 * Test that sanitize_options() works with WordPress sanitize_option filter
	 * 
	 * This simulates how WordPress calls the sanitization callback via the filter
	 */
	public function testSanitizeOptionsViaWordPressFilter() {
		// Register a test section
		$this->registry->register_section(
			'test_filter_section',
			[
				'title' => 'Test Filter Section',
			]
		);

		// Initialize to register the setting
		$this->registry->admin_init();

		// Simulate WordPress calling the sanitize_option filter with a string
		// This is what happens when updating via /wp-admin/options.php
		$filtered_value = apply_filters( 'sanitize_option_test_filter_section', '' );

		// Should not throw an error and should return the value
		$this->assertSame( '', $filtered_value );

		// Test with a non-empty string
		$filtered_value = apply_filters( 'sanitize_option_test_filter_section', 'test_value' );
		$this->assertSame( 'test_value', $filtered_value );
	}
}

