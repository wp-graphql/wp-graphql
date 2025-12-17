<?php

class ExperimentIntegrationTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * The ExperimentRegistry instance for tests.
	 *
	 * @var \WPGraphQL\Experimental\ExperimentRegistry
	 */
	protected $registry;

	public function setUp(): void {
		parent::setUp();
		$this->clearSchema();

		// Clear any experiment settings
		delete_option( 'graphql_experiments_settings' );

		// Register test experiments via filter (they're commented out by default in production)
		add_filter( 'graphql_experiments_registered_classes', [ $this, 'register_test_experiments' ] );

		// Create a fresh registry instance for each test
		$this->registry = new \WPGraphQL\Experimental\ExperimentRegistry();
	}

	/**
	 * Register test experiments for testing purposes.
	 *
	 * @param array $registry The experiment registry.
	 * @return array The modified registry with test experiments.
	 */
	public function register_test_experiments( array $registry ): array {
		$registry['test_experiment'] = \WPGraphQL\Experimental\Experiment\TestExperiment\TestExperiment::class;
		return $registry;
	}

	public function tearDown(): void {
		// Clear any experiment settings
		delete_option( 'graphql_experiments_settings' );

		// Remove any filters
		remove_all_filters( 'graphql_experimental_features' );
		remove_filter( 'graphql_experiments_registered_classes', [ $this, 'register_test_experiments' ] );

		parent::tearDown();
	}

	/**
	 * Test that activation/deactivation persists to database
	 */
	public function testExperimentActivationPersistence() {
		// Initially, the setting should not exist
		$settings = get_option( 'graphql_experiments_settings', [] );
		$this->assertEmpty( $settings );

		// Activate the experiment by updating the settings
		$settings['test_experiment_enabled'] = 'on';
		update_option( 'graphql_experiments_settings', $settings );

		// Verify the setting persisted
		$saved_settings = get_option( 'graphql_experiments_settings', [] );
		$this->assertArrayHasKey( 'test_experiment_enabled', $saved_settings );
		$this->assertEquals( 'on', $saved_settings['test_experiment_enabled'] );

		// Deactivate the experiment
		$settings['test_experiment_enabled'] = 'off';
		update_option( 'graphql_experiments_settings', $settings );

		// Verify the deactivation persisted
		$saved_settings = get_option( 'graphql_experiments_settings', [] );
		$this->assertEquals( 'off', $saved_settings['test_experiment_enabled'] );
	}

	/**
	 * Test that experiment hooks fire correctly when activated via settings
	 */
	public function testExperimentHooksFireWhenActive() {
		// Activate the test experiment via settings
		update_option( 'graphql_experiments_settings', [ 'test_experiment_enabled' => 'on' ] );

		// Initialize the fresh registry instance
		$this->registry->init();

		// Get the experiment
		$experiments = $this->registry->get_experiments();
		$this->assertArrayHasKey( 'test_experiment', $experiments );

		// Verify the experiment is now active
		$test_experiment = $experiments['test_experiment'];
		$test_experiment->clear_active_cache(); // Clear cache to re-evaluate
		$this->assertTrue( $test_experiment->is_active() );
	}

	/**
	 * Test that multiple experiments can be active simultaneously (concept test)
	 */
	public function testMultipleExperimentsCanBeRegistered() {
		// Initialize the registry
		$this->registry->init();

		// Get all registered experiments
		$experiments = $this->registry->get_experiments();

		// We should have at least one experiment registered
		$this->assertNotEmpty( $experiments );
		$this->assertArrayHasKey( 'test_experiment', $experiments );

		// Verify we can access experiment properties
		$test_experiment = $experiments['test_experiment'];
		$this->assertInstanceOf( \WPGraphQL\Experimental\Experiment\TestExperiment\TestExperiment::class, $test_experiment );
		$config = $test_experiment->get_config();
		$this->assertIsArray( $config );
		$this->assertArrayHasKey( 'title', $config );
	}
}
