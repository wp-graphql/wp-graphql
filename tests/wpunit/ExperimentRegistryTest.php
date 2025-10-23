<?php

class ExperimentRegistryTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->clearSchema();

		// Clear any experiment settings to ensure clean state
		update_option( 'graphql_experiments_settings', [] );

		// Reset the registry to ensure a clean slate
		\WPGraphQL\Experimental\ExperimentRegistry::reset();
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

		// Reset the registry again
		\WPGraphQL\Experimental\ExperimentRegistry::reset();

		parent::tearDown();
	}

	/**
	 * Test that experiments are registered correctly
	 */
	public function testExperimentRegistration() {
		$registry = new \WPGraphQL\Experimental\ExperimentRegistry();
		$registry->init();

		$experiments = \WPGraphQL\Experimental\ExperimentRegistry::get_experiments();
		$this->assertIsArray( $experiments );
		$this->assertArrayHasKey( 'test_experiment', $experiments );
		$this->assertInstanceOf( \WPGraphQL\Experimental\Experiment\TestExperiment\TestExperiment::class, $experiments['test_experiment'] );
	}

	/**
	 * Test that experiments can be activated and deactivated
	 */
	public function testExperimentActivation() {
		// Initialize the registry
		$registry = new \WPGraphQL\Experimental\ExperimentRegistry();
		$registry->init();

		// Test that the experiment is initially inactive
		$this->assertFalse( \WPGraphQL\Experimental\ExperimentRegistry::is_experiment_active( 'test_experiment' ) );

		// Test that we can get the experiment instance
		$experiments = \WPGraphQL\Experimental\ExperimentRegistry::get_experiments();
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
		$registry = new \WPGraphQL\Experimental\ExperimentRegistry();
		$registry->init();

		$experiments = \WPGraphQL\Experimental\ExperimentRegistry::get_experiments();
		$this->assertArrayHasKey( 'test-dependant-experiment', $experiments );
		$this->assertInstanceOf( \WPGraphQL\Experimental\Experiment\TestDependantExperiment\TestDependantExperiment::class, $experiments['test-dependant-experiment'] );
	}

	/**
	 * Test that dependent experiments cannot be loaded without their dependencies
	 */
	public function testDependentExperimentRequiresDependency() {
		// Initialize the registry
		$registry = new \WPGraphQL\Experimental\ExperimentRegistry();
		$registry->init();

		// Activate only the dependent experiment (not its dependency)
		$settings = get_option( 'graphql_experiments_settings', [] );
		$settings['test-dependant-experiment_enabled'] = 'on';
		update_option( 'graphql_experiments_settings', $settings );

		// Reload experiments to pick up the new settings
		$registry->reload_experiments();

		// The dependent experiment should not be active because its dependency is not active
		$this->assertFalse( \WPGraphQL\Experimental\ExperimentRegistry::is_experiment_active( 'test-dependant-experiment' ) );

		// The dependent experiment should be registered but not loaded
		$registered_experiments = \WPGraphQL\Experimental\ExperimentRegistry::get_experiment_registry();
		$this->assertArrayHasKey( 'test-dependant-experiment', $registered_experiments );
		
		$active_experiments = \WPGraphQL\Experimental\ExperimentRegistry::get_active_experiments();
		$this->assertArrayNotHasKey( 'test-dependant-experiment', $active_experiments );
	}

	/**
	 * Test that dependent experiments can be loaded when their dependencies are active
	 */
	public function testDependentExperimentLoadsWithDependency() {
		// Initialize the registry
		$registry = new \WPGraphQL\Experimental\ExperimentRegistry();
		$registry->init();

		// Activate both the dependency and the dependent experiment
		$settings = get_option( 'graphql_experiments_settings', [] );
		$settings['test_experiment_enabled'] = 'on';
		$settings['test-dependant-experiment_enabled'] = 'on';
		update_option( 'graphql_experiments_settings', $settings );

		// Reload experiments to pick up the new settings
		$registry->reload_experiments();


		// Both experiments should be active
		$this->assertTrue( \WPGraphQL\Experimental\ExperimentRegistry::is_experiment_active( 'test_experiment' ) );
		$this->assertTrue( \WPGraphQL\Experimental\ExperimentRegistry::is_experiment_active( 'test-dependant-experiment' ) );

		// Both experiments should be in the active experiments array
		$active_experiments = \WPGraphQL\Experimental\ExperimentRegistry::get_active_experiments();
		$this->assertArrayHasKey( 'test_experiment', $active_experiments );
		$this->assertArrayHasKey( 'test-dependant-experiment', $active_experiments );
	}

	/**
	 * Test that dependent experiments are deactivated when their dependencies are deactivated
	 */
	public function testDependentExperimentDeactivatedWhenDependencyDeactivated() {
		// Initialize the registry
		$registry = new \WPGraphQL\Experimental\ExperimentRegistry();
		$registry->init();

		// Activate both experiments
		$settings = get_option( 'graphql_experiments_settings', [] );
		$settings['test_experiment_enabled'] = 'on';
		$settings['test-dependant-experiment_enabled'] = 'on';
		update_option( 'graphql_experiments_settings', $settings );

		// Reload experiments
		$registry->reload_experiments();

		// Both should be active
		$this->assertTrue( \WPGraphQL\Experimental\ExperimentRegistry::is_experiment_active( 'test_experiment' ) );
		$this->assertTrue( \WPGraphQL\Experimental\ExperimentRegistry::is_experiment_active( 'test-dependant-experiment' ) );

		// Deactivate the dependency
		$settings['test_experiment_enabled'] = 'off';
		update_option( 'graphql_experiments_settings', $settings );

		// Reload experiments
		$registry->reload_experiments();

		// The dependency should be inactive
		$this->assertFalse( \WPGraphQL\Experimental\ExperimentRegistry::is_experiment_active( 'test_experiment' ) );
		
		// The dependent experiment should also be inactive (even though it's still enabled in settings)
		$this->assertFalse( \WPGraphQL\Experimental\ExperimentRegistry::is_experiment_active( 'test-dependant-experiment' ) );
	}

	/**
	 * Test that experiments can specify their dependencies correctly
	 */
	public function testExperimentDependencies() {
		$registry = new \WPGraphQL\Experimental\ExperimentRegistry();
		$registry->init();

		$experiments = \WPGraphQL\Experimental\ExperimentRegistry::get_experiments();
		
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
		$registry = new \WPGraphQL\Experimental\ExperimentRegistry();
		$registry->init();

		// Activate only the optional dependency experiment (not its optional dependency)
		$settings = get_option( 'graphql_experiments_settings', [] );
		$settings['test-optional-dependency-experiment_enabled'] = 'on';
		update_option( 'graphql_experiments_settings', $settings );

		// Reload experiments to pick up the new settings
		$registry->reload_experiments();

		// The optional dependency experiment should be active even without its optional dependency
		$this->assertTrue( \WPGraphQL\Experimental\ExperimentRegistry::is_experiment_active( 'test-optional-dependency-experiment' ) );

		// It should be in the active experiments array
		$active_experiments = \WPGraphQL\Experimental\ExperimentRegistry::get_active_experiments();
		$this->assertArrayHasKey( 'test-optional-dependency-experiment', $active_experiments );
	}

	/**
	 * Test that GraphQL fields are registered when experiments are active
	 */
	public function testGraphQLFieldRegistration() {
		// Initialize the registry
		$registry = new \WPGraphQL\Experimental\ExperimentRegistry();
		$registry->init();

		// Activate TestExperiment
		$settings = get_option( 'graphql_experiments_settings', [] );
		$settings['test_experiment_enabled'] = 'on';
		update_option( 'graphql_experiments_settings', $settings );

		// Reload experiments
		$registry->reload_experiments();

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
		$registry = new \WPGraphQL\Experimental\ExperimentRegistry();
		$registry->init();

		// Activate both TestExperiment and TestDependantExperiment
		$settings = get_option( 'graphql_experiments_settings', [] );
		$settings['test_experiment_enabled'] = 'on';
		$settings['test-dependant-experiment_enabled'] = 'on';
		update_option( 'graphql_experiments_settings', $settings );

		// Reload experiments
		$registry->reload_experiments();

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
		$registry = new \WPGraphQL\Experimental\ExperimentRegistry();
		$registry->init();

		// Activate only TestOptionalDependencyExperiment
		$settings = get_option( 'graphql_experiments_settings', [] );
		$settings['test-optional-dependency-experiment_enabled'] = 'on';
		update_option( 'graphql_experiments_settings', $settings );

		// Reload experiments
		$registry->reload_experiments();

		// The testOptionalDependency field should be available
		$schema = \WPGraphQL::get_schema();
		$root_query = $schema->getType( 'RootQuery' );
		$fields = $root_query->getFields();
		
		$this->assertArrayHasKey( 'testOptionalDependency', $fields );

		// Now activate TestExperiment as well
		$settings['test_experiment_enabled'] = 'on';
		update_option( 'graphql_experiments_settings', $settings );

		// Reload experiments
		$registry->reload_experiments();

		// Clear schema cache to pick up new fields
		$this->clearSchema();

		// Both fields should be available
		$schema = \WPGraphQL::get_schema();
		$root_query = $schema->getType( 'RootQuery' );
		$fields = $root_query->getFields();
		
		$this->assertArrayHasKey( 'testExperiment', $fields );
		$this->assertArrayHasKey( 'testOptionalDependency', $fields );
	}
}
