<?php

class ExperimentRegistryTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * The ExperimentRegistry instance for tests.
	 *
	 * @var \WPGraphQL\Experimental\ExperimentRegistry
	 */
	protected $registry;

	public function setUp(): void {
		parent::setUp();
		$this->clearSchema();

		// Clear any experiment settings to ensure clean state
		update_option( 'graphql_experiments_settings', [] );

		// Create a fresh registry instance for each test
		$this->registry = new \WPGraphQL\Experimental\ExperimentRegistry();
	}

	public function tearDown(): void {
		// Clear the schema to ensure clean state between tests
		$this->clearSchema();

		// Clear any experiment settings
		$settings = get_option( 'graphql_experiments_settings', [] );
		$settings['test_experiment_enabled'] = 'off';
		$settings['test-dependant-experiment_enabled'] = 'off';
		$settings['test-optional-dependency-experiment_enabled'] = 'off';
		update_option( 'graphql_experiments_settings', $settings );

		parent::tearDown();
	}

	/**
	 * Test that experiments are registered correctly
	 */
	public function testExperimentRegistration() {
		$this->registry->init();

		$experiments = $this->registry->get_experiments();
		$this->assertIsArray( $experiments );
		$this->assertArrayHasKey( 'test_experiment', $experiments );
		$this->assertInstanceOf( \WPGraphQL\Experimental\Experiment\TestExperiment\TestExperiment::class, $experiments['test_experiment'] );
	}

	/**
	 * Test that experiments can be activated and deactivated
	 */
	public function testExperimentActivation() {
		// Initialize the registry
		$this->registry->init();

		// Test that the experiment is initially inactive
		$this->assertFalse( $this->registry->is_experiment_active( 'test_experiment' ) );

		// Test that we can get the experiment instance
		$experiments = $this->registry->get_experiments();
		$this->assertArrayHasKey( 'test_experiment', $experiments );

		$test_experiment = $experiments['test_experiment'];
		$this->assertInstanceOf( \WPGraphQL\Experimental\Experiment\TestExperiment\TestExperiment::class, $test_experiment );

		// Test that the experiment reports as inactive initially
		$this->assertFalse( $test_experiment->is_active() );
	}

	/**
	 * Test that dependent experiments are registered correctly
	 */
	public function testDependentExperimentRegistration() {
		$this->registry->init();

		$experiments = $this->registry->get_experiments();
		$this->assertArrayHasKey( 'test-dependant-experiment', $experiments );
		$this->assertInstanceOf( \WPGraphQL\Experimental\Experiment\TestDependantExperiment\TestDependantExperiment::class, $experiments['test-dependant-experiment'] );
	}

	/**
	 * Test that dependent experiments cannot be loaded without their dependencies
	 */
	public function testDependentExperimentRequiresDependency() {
		// Initialize the registry
		$this->registry->init();

		// Activate only the dependent experiment (not its dependency)
		$settings = get_option( 'graphql_experiments_settings', [] );
		$settings['test-dependant-experiment_enabled'] = 'on';
		update_option( 'graphql_experiments_settings', $settings );

		// Reload experiments to pick up the new settings
		$this->registry->reload_experiments();

		// The dependent experiment should not be active because its dependency is not active
		$this->assertFalse( $this->registry->is_experiment_active( 'test-dependant-experiment' ) );

		// The dependent experiment should be registered but not loaded
		$registered_experiments = $this->registry->get_experiment_registry();
		$this->assertArrayHasKey( 'test-dependant-experiment', $registered_experiments );
		
		$active_experiments = $this->registry->get_active_experiments();
		$this->assertArrayNotHasKey( 'test-dependant-experiment', $active_experiments );
	}

	/**
	 * Test that dependent experiments can be loaded when their dependencies are active
	 */
	public function testDependentExperimentLoadsWithDependency() {
		// Initialize the registry
		$this->registry->init();

		// Activate both the dependency and the dependent experiment
		$settings = get_option( 'graphql_experiments_settings', [] );
		$settings['test_experiment_enabled'] = 'on';
		$settings['test-dependant-experiment_enabled'] = 'on';
		update_option( 'graphql_experiments_settings', $settings );

		// Reload experiments to pick up the new settings
		$this->registry->reload_experiments();


		// Both experiments should be active
		$this->assertTrue( $this->registry->is_experiment_active( 'test_experiment' ) );
		$this->assertTrue( $this->registry->is_experiment_active( 'test-dependant-experiment' ) );

		// Both experiments should be in the active experiments array
		$active_experiments = $this->registry->get_active_experiments();
		$this->assertArrayHasKey( 'test_experiment', $active_experiments );
		$this->assertArrayHasKey( 'test-dependant-experiment', $active_experiments );
	}

	/**
	 * Test that dependent experiments are deactivated when their dependencies are deactivated
	 */
	public function testDependentExperimentDeactivatedWhenDependencyDeactivated() {
		// Initialize the registry
		$this->registry->init();

		// Activate both experiments
		$settings = get_option( 'graphql_experiments_settings', [] );
		$settings['test_experiment_enabled'] = 'on';
		$settings['test-dependant-experiment_enabled'] = 'on';
		update_option( 'graphql_experiments_settings', $settings );

		// Reload experiments
		$this->registry->reload_experiments();

		// Both should be active
		$this->assertTrue( $this->registry->is_experiment_active( 'test_experiment' ) );
		$this->assertTrue( $this->registry->is_experiment_active( 'test-dependant-experiment' ) );

		// Deactivate the dependency
		$settings['test_experiment_enabled'] = 'off';
		update_option( 'graphql_experiments_settings', $settings );

		// Reload experiments
		$this->registry->reload_experiments();

		// The dependency should be inactive
		$this->assertFalse( $this->registry->is_experiment_active( 'test_experiment' ) );
		
		// The dependent experiment should also be inactive (even though it's still enabled in settings)
		$this->assertFalse( $this->registry->is_experiment_active( 'test-dependant-experiment' ) );
	}

	/**
	 * Test that experiments can specify their dependencies correctly
	 */
	public function testExperimentDependencies() {
		$this->registry->init();

		$experiments = $this->registry->get_experiments();
		
		// TestExperiment should have no dependencies
		$test_experiment = $experiments['test_experiment'];
		$dependencies = $test_experiment->get_dependencies();
		$this->assertEmpty( $dependencies['required'] );
		$this->assertEmpty( $dependencies['optional'] );

		// TestDependantExperiment should depend on TestExperiment
		$dependent_experiment = $experiments['test-dependant-experiment'];
		$dependencies = $dependent_experiment->get_dependencies();
		$this->assertContains( 'test_experiment', $dependencies['required'] );
		$this->assertEmpty( $dependencies['optional'] );

		// TestOptionalDependencyExperiment should have TestExperiment as optional dependency
		$optional_dependent_experiment = $experiments['test-optional-dependency-experiment'];
		$dependencies = $optional_dependent_experiment->get_dependencies();
		$this->assertEmpty( $dependencies['required'] );
		$this->assertContains( 'test_experiment', $dependencies['optional'] );
	}

	/**
	 * Test that optional dependency experiments work independently
	 */
	public function testOptionalDependencyExperimentWorksIndependently() {
		// Initialize the registry
		$this->registry->init();

		// Activate only the optional dependency experiment (not its optional dependency)
		$settings = get_option( 'graphql_experiments_settings', [] );
		$settings['test-optional-dependency-experiment_enabled'] = 'on';
		update_option( 'graphql_experiments_settings', $settings );

		// Reload experiments to pick up the new settings
		$this->registry->reload_experiments();

		// The optional dependency experiment should be active even without its optional dependency
		$this->assertTrue( $this->registry->is_experiment_active( 'test-optional-dependency-experiment' ) );

		// It should be in the active experiments array
		$active_experiments = $this->registry->get_active_experiments();
		$this->assertArrayHasKey( 'test-optional-dependency-experiment', $active_experiments );
	}

	/**
	 * Test that GraphQL fields are registered when experiments are active
	 */
	public function testGraphQLFieldRegistration() {
		// Initialize the registry
		$this->registry->init();

		// Activate TestExperiment
		$settings = get_option( 'graphql_experiments_settings', [] );
		$settings['test_experiment_enabled'] = 'on';
		update_option( 'graphql_experiments_settings', $settings );

		// Reload experiments
		$this->registry->reload_experiments();

		// The testExperiment field should be available in the schema
		$schema = \WPGraphQL::get_schema();
		$root_query = $schema->getType( 'RootQuery' );
		$fields = $root_query->getFields();
		
		$this->assertArrayHasKey( 'testExperiment', $fields );
	}

	/**
	 * Test that dependent GraphQL fields are registered when both experiments are active
	 */
	public function testDependentGraphQLFieldRegistration() {
		// Initialize the registry
		$this->registry->init();

		// Activate both TestExperiment and TestDependantExperiment
		$settings = get_option( 'graphql_experiments_settings', [] );
		$settings['test_experiment_enabled'] = 'on';
		$settings['test-dependant-experiment_enabled'] = 'on';
		update_option( 'graphql_experiments_settings', $settings );

		// Reload experiments
		$this->registry->reload_experiments();

		// Both fields should be available in the schema
		$schema = \WPGraphQL::get_schema();
		$root_query = $schema->getType( 'RootQuery' );
		$fields = $root_query->getFields();
		
		$this->assertArrayHasKey( 'testExperiment', $fields );
		$this->assertArrayHasKey( 'testDependantExperiment', $fields );
	}

	/**
	 * Test that optional dependency GraphQL fields work with and without the optional dependency
	 */
	public function testOptionalDependencyGraphQLFieldRegistration() {
		// Initialize the registry
		$this->registry->init();

		// Activate only TestOptionalDependencyExperiment
		$settings = get_option( 'graphql_experiments_settings', [] );
		$settings['test-optional-dependency-experiment_enabled'] = 'on';
		update_option( 'graphql_experiments_settings', $settings );

		// Reload experiments
		$this->registry->reload_experiments();

		// The testOptionalDependency field should be available
		$schema = \WPGraphQL::get_schema();
		$root_query = $schema->getType( 'RootQuery' );
		$fields = $root_query->getFields();
		
		$this->assertArrayHasKey( 'testOptionalDependency', $fields );

		// Now activate TestExperiment as well
		$settings['test_experiment_enabled'] = 'on';
		update_option( 'graphql_experiments_settings', $settings );

		// Reload experiments
		$this->registry->reload_experiments();

		// Clear schema cache to pick up new fields
		$this->clearSchema();

		// Both fields should be available
		$schema = \WPGraphQL::get_schema();
		$root_query = $schema->getType( 'RootQuery' );
		$fields = $root_query->getFields();
		
		$this->assertArrayHasKey( 'testExperiment', $fields );
		$this->assertArrayHasKey( 'testOptionalDependency', $fields );
	}

	/**
	 * Test that experiments can be registered programmatically
	 */
	public function testRegisterExperimentProgrammatically() {
		// Initialize the registry
		$this->registry->init();

		// Get initial count of registered experiments
		$initial_experiments = $this->registry->get_registered_experiments();
		$initial_count = count( $initial_experiments );

		// Register a new experiment programmatically
		$this->registry->register_experiment( 'custom-test-experiment', \WPGraphQL\Experimental\Experiment\TestExperiment\TestExperiment::class );

		// Verify the experiment was registered
		$registered_experiments = $this->registry->get_registered_experiments();
		$this->assertCount( $initial_count + 1, $registered_experiments );
		$this->assertArrayHasKey( 'custom-test-experiment', $registered_experiments );

		// Verify we can get the registered experiment class
		$experiment_class = $this->registry->get_registered_experiment( 'custom-test-experiment' );
		$this->assertEquals( \WPGraphQL\Experimental\Experiment\TestExperiment\TestExperiment::class, $experiment_class );
	}

	/**
	 * Test that experiments can be unregistered programmatically
	 */
	public function testUnregisterExperimentProgrammatically() {
		// Initialize the registry
		$this->registry->init();

		// Verify test_experiment is registered
		$this->assertArrayHasKey( 'test_experiment', $this->registry->get_registered_experiments() );
		$this->assertArrayHasKey( 'test_experiment', $this->registry->get_experiments() );

		// Unregister the experiment
		$this->registry->unregister_experiment( 'test_experiment' );

		// Verify the experiment was unregistered from all arrays
		$this->assertArrayNotHasKey( 'test_experiment', $this->registry->get_registered_experiments() );
		$this->assertArrayNotHasKey( 'test_experiment', $this->registry->get_experiments() );
		$this->assertArrayNotHasKey( 'test_experiment', $this->registry->get_active_experiments() );

		// Verify get_registered_experiment returns null for unregistered experiment
		$this->assertNull( $this->registry->get_registered_experiment( 'test_experiment' ) );
	}

	/**
	 * Test that get_registered_experiment returns null for non-existent experiments
	 */
	public function testGetRegisteredExperimentReturnsNullForNonExistent() {
		$this->registry->init();

		$experiment_class = $this->registry->get_registered_experiment( 'non-existent-experiment' );
		$this->assertNull( $experiment_class );
	}

	/**
	 * Test that each test has an isolated registry instance
	 */
	public function testRegistryIsolation() {
		// This test verifies that changes in one test don't affect others
		// by checking that we start with a fresh registry
		$this->registry->init();

		// Register a custom experiment
		$this->registry->register_experiment( 'isolation-test-experiment', \WPGraphQL\Experimental\Experiment\TestExperiment\TestExperiment::class );

		// Verify it's registered on this instance
		$this->assertArrayHasKey( 'isolation-test-experiment', $this->registry->get_registered_experiments() );

		// Create a new registry instance to verify isolation
		$new_registry = new \WPGraphQL\Experimental\ExperimentRegistry();
		$new_registry->init();

		// The custom experiment should not exist on the new instance
		$this->assertArrayNotHasKey( 'isolation-test-experiment', $new_registry->get_registered_experiments() );
	}
}
