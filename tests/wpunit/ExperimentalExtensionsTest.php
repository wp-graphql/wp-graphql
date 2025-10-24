<?php

use WPGraphQL\Experimental\ExperimentRegistry;
use WPGraphQL\Experimental\Extensions;

/**
 * Class ExperimentalExtensionsTest
 *
 * Tests for the GraphQL Extensions Response functionality.
 *
 * @package WPGraphQL\Tests\WPUnit
 * @since 2.3.8
 */
class ExperimentalExtensionsTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * The Extensions instance.
	 *
	 * @var \WPGraphQL\Experimental\Extensions
	 */
	protected $extensions;

	/**
	 * The Experiment Registry instance.
	 *
	 * @var \WPGraphQL\Experimental\ExperimentRegistry
	 */
	protected $registry;

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		
		// Clear any existing experiments
		ExperimentRegistry::reset();
		
		// Create instances
		$this->extensions = new Extensions();
		$this->registry = new ExperimentRegistry();
		$this->registry->init();
	}

	/**
	 * Clean up after each test.
	 */
	public function tearDown(): void {
		// Clear experiments
		ExperimentRegistry::reset();
		
		// Clear schema
		$this->clearSchema();
		
		parent::tearDown();
	}

	/**
	 * Test that experiments are added to GraphQL response extensions when debug is enabled.
	 */
	public function testExperimentsAddedToResponseExtensionsWhenDebugEnabled(): void {
		// Activate test experiment
		update_option( 'graphql_experiments_settings', [
			'test_experiment_enabled' => 'on',
		] );

		// Reload experiments to pick up the activation
		$this->registry->reload_experiments();

		// Mock response array
		$response = [
			'data' => [
				'testExperiment' => 'test value',
			],
		];

		// Mock schema
		$schema = \WPGraphQL::get_schema();

		// Call the method
		$result = $this->extensions->add_experiments_to_response_extensions(
			$response,
			$schema,
			'testOperation',
			'query { testExperiment }',
			[]
		);

		// Assert experiments are in extensions (debug is enabled by default in tests)
		$this->assertArrayHasKey( 'extensions', $result );
		$this->assertArrayHasKey( 'experiments', $result['extensions'] );
		$this->assertContains( 'test_experiment', $result['extensions']['experiments'] );
	}

	/**
	 * Test that experiments are added to GraphQL response extensions when using object response and debug is enabled.
	 */
	public function testExperimentsAddedToObjectResponseExtensionsWhenDebugEnabled(): void {
		// Activate test experiment
		update_option( 'graphql_experiments_settings', [
			'test_experiment_enabled' => 'on',
		] );

		// Reload experiments to pick up the activation
		$this->registry->reload_experiments();

		// Mock response object
		$response = (object) [
			'data' => [
				'testExperiment' => 'test value',
			],
		];

		// Mock schema
		$schema = \WPGraphQL::get_schema();

		// Call the method
		$result = $this->extensions->add_experiments_to_response_extensions(
			$response,
			$schema,
			'testOperation',
			'query { testExperiment }',
			[]
		);

		// Assert experiments are in extensions (debug is enabled by default in tests)
		$this->assertTrue( property_exists( $result, 'extensions' ) );
		$this->assertTrue( is_array( $result->extensions ) );
		$this->assertArrayHasKey( 'experiments', $result->extensions );
		$this->assertContains( 'test_experiment', $result->extensions['experiments'] );
	}

	/**
	 * Test that experiments are NOT added to GraphQL response extensions when debug is disabled.
	 */
	public function testExperimentsNotAddedWhenDebugDisabled(): void {
		// Disable GraphQL debugging
		add_filter( 'graphql_debug_enabled', '__return_false' );

		// Activate test experiment
		update_option( 'graphql_experiments_settings', [
			'test_experiment_enabled' => 'on',
		] );

		// Reload experiments to pick up the activation
		$this->registry->reload_experiments();

		// Mock response array
		$response = [
			'data' => [
				'testExperiment' => 'test value',
			],
		];

		// Mock schema
		$schema = \WPGraphQL::get_schema();

		// Call the method
		$result = $this->extensions->add_experiments_to_response_extensions(
			$response,
			$schema,
			'testOperation',
			'query { testExperiment }',
			[]
		);

		// Assert experiments are NOT in extensions when debug is disabled
		$this->assertArrayNotHasKey( 'extensions', $result );

		// Clean up
		remove_filter( 'graphql_debug_enabled', '__return_false' );
	}

	/**
	 * Test that no experiments are added when none are active.
	 */
	public function testNoExperimentsAddedWhenNoneActive(): void {
		// Ensure no experiments are active
		update_option( 'graphql_experiments_settings', [] );

		// Reload experiments
		$this->registry->reload_experiments();

		// Mock response array
		$response = [
			'data' => [
				'__typename' => 'RootQuery',
			],
		];

		// Mock schema
		$schema = \WPGraphQL::get_schema();

		// Call the method
		$result = $this->extensions->add_experiments_to_response_extensions(
			$response,
			$schema,
			'testOperation',
			'query { __typename }',
			[]
		);

		// Assert no experiments are in extensions
		$this->assertArrayNotHasKey( 'extensions', $result );
	}

	/**
	 * Test that multiple active experiments are included in extensions.
	 */
	public function testMultipleActiveExperimentsInExtensions(): void {
		// Activate multiple experiments
		update_option( 'graphql_experiments_settings', [
			'test_experiment_enabled' => 'on',
			'test-dependant-experiment_enabled' => 'on',
			'test-optional-dependency-experiment_enabled' => 'on',
		] );

		// Reload experiments to pick up the activations
		$this->registry->reload_experiments();

		// Mock response array
		$response = [
			'data' => [
				'testExperiment' => 'test value',
			],
		];

		// Mock schema
		$schema = \WPGraphQL::get_schema();

		// Call the method
		$result = $this->extensions->add_experiments_to_response_extensions(
			$response,
			$schema,
			'testOperation',
			'query { testExperiment }',
			[]
		);

		// Assert all experiments are in extensions
		$this->assertArrayHasKey( 'extensions', $result );
		$this->assertArrayHasKey( 'experiments', $result['extensions'] );
		$experiments = $result['extensions']['experiments'];
		
		$this->assertContains( 'test_experiment', $experiments );
		$this->assertContains( 'test-dependant-experiment', $experiments );
		$this->assertContains( 'test-optional-dependency-experiment', $experiments );
		$this->assertCount( 3, $experiments );
	}

	/**
	 * Test that the filter can disable experiments in extensions.
	 */
	public function testFilterCanDisableExperimentsInExtensions(): void {
		// Activate test experiment
		update_option( 'graphql_experiments_settings', [
			'test_experiment_enabled' => 'on',
		] );

		// Reload experiments to pick up the activation
		$this->registry->reload_experiments();

		// Add filter to disable experiments in extensions
		add_filter( 'graphql_should_show_experiments_in_extensions', '__return_false' );

		// Mock response array
		$response = [
			'data' => [
				'testExperiment' => 'test value',
			],
		];

		// Mock schema
		$schema = \WPGraphQL::get_schema();

		// Call the method
		$result = $this->extensions->add_experiments_to_response_extensions(
			$response,
			$schema,
			'testOperation',
			'query { testExperiment }',
			[]
		);

		// Assert no experiments are in extensions due to filter
		$this->assertArrayNotHasKey( 'extensions', $result );

		// Clean up filter
		remove_filter( 'graphql_should_show_experiments_in_extensions', '__return_false' );
	}

	/**
	 * Test that empty response is handled gracefully.
	 */
	public function testEmptyResponseHandledGracefully(): void {
		// Activate test experiment
		update_option( 'graphql_experiments_settings', [
			'test_experiment_enabled' => 'on',
		] );

		// Reload experiments to pick up the activation
		$this->registry->reload_experiments();

		// Mock empty response
		$response = [];

		// Mock schema
		$schema = \WPGraphQL::get_schema();

		// Call the method
		$result = $this->extensions->add_experiments_to_response_extensions(
			$response,
			$schema,
			'testOperation',
			'query { testExperiment }',
			[]
		);

		// Assert response is returned unchanged when empty
		$this->assertEquals( $response, $result );
	}
}
