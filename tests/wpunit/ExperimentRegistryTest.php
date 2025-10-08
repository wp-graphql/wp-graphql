<?php

class ExperimentRegistryTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->clearSchema();
	}

	public function tearDown(): void {
		// Clear any experiment settings
		$settings = get_option( 'graphql_experiments_settings', [] );
		$settings['test_experiment_enabled'] = 'off';
		update_option( 'graphql_experiments_settings', $settings );

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
		$this->assertInstanceOf( \WPGraphQL\Experimental\Experiment\TestExperiment::class, $experiments['test_experiment'] );
	}

	/**
	 * Test that experiments can be activated and deactivated
	 */
	public function testExperimentActivation() {
		// Test that the experiment is initially inactive
		$this->assertFalse( \WPGraphQL\Experimental\ExperimentRegistry::is_experiment_active( 'test_experiment' ) );

		// Test that we can get the experiment instance
		$experiments = \WPGraphQL\Experimental\ExperimentRegistry::get_experiments();
		$this->assertArrayHasKey( 'test_experiment', $experiments );

		$test_experiment = $experiments['test_experiment'];
		$this->assertInstanceOf( \WPGraphQL\Experimental\Experiment\TestExperiment::class, $test_experiment );

		// Test that the experiment reports as inactive initially
		$this->assertFalse( $test_experiment->is_active() );
	}
}
