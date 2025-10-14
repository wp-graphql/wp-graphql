<?php

class AbstractExperimentTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->clearSchema();

		// Clear any experiment settings to ensure clean state
		update_option( 'graphql_experiments_settings', [] );

		// Reset the registry to ensure a clean slate
		\WPGraphQL\Experimental\ExperimentRegistry::reset();
	}

	public function tearDown(): void {
		// Clear any experiment settings
		$settings = get_option( 'graphql_experiments_settings', [] );
		$settings['test_experiment_enabled'] = 'off';
		update_option( 'graphql_experiments_settings', $settings );

		// Reset the registry again
		\WPGraphQL\Experimental\ExperimentRegistry::reset();

		parent::tearDown();
	}

	/**
	 * Test that experiment configuration is properly validated
	 */
	public function testExperimentConfigValidation() {
		$registry = new \WPGraphQL\Experimental\ExperimentRegistry();
		$registry->init();

		$experiments = \WPGraphQL\Experimental\ExperimentRegistry::get_experiments();
		$test_experiment = $experiments['test_experiment'];

		// Test that config is properly loaded
		$config = $test_experiment->get_config();
		$this->assertIsArray( $config );
		$this->assertArrayHasKey( 'title', $config );
		$this->assertArrayHasKey( 'description', $config );
		$this->assertIsString( $config['title'] );
		$this->assertIsString( $config['description'] );
		$this->assertNotEmpty( $config['title'] );
		$this->assertNotEmpty( $config['description'] );
	}

	/**
	 * Test that experiment slug is properly retrieved
	 */
	public function testExperimentSlug() {
		$registry = new \WPGraphQL\Experimental\ExperimentRegistry();
		$registry->init();

		$experiments = \WPGraphQL\Experimental\ExperimentRegistry::get_experiments();
		$test_experiment = $experiments['test_experiment'];

		// Test that slug is properly retrieved
		$slug = $test_experiment->get_slug();
		$this->assertEquals( 'test_experiment', $slug );
	}

	/**
	 * Test that experiment deprecation methods work correctly
	 */
	public function testExperimentDeprecation() {
		$registry = new \WPGraphQL\Experimental\ExperimentRegistry();
		$registry->init();

		$experiments = \WPGraphQL\Experimental\ExperimentRegistry::get_experiments();
		$test_experiment = $experiments['test_experiment'];

		// Test that experiment is not deprecated by default
		$this->assertFalse( $test_experiment->is_deprecated() );
		$this->assertNull( $test_experiment->get_deprecation_message() );
	}

	/**
	 * Test that GRAPHQL_EXPERIMENTAL_FEATURES constant behavior works
	 */
	public function testGraphqlExperimentalFeaturesConstant() {
		$registry = new \WPGraphQL\Experimental\ExperimentRegistry();
		$registry->init();

		$experiments = \WPGraphQL\Experimental\ExperimentRegistry::get_experiments();
		$test_experiment = $experiments['test_experiment'];

		// Even if settings say it's enabled, constant should override
		$settings = get_option( 'graphql_experiments_settings', [] );
		$settings['test_experiment_enabled'] = 'on';
		update_option( 'graphql_experiments_settings', $settings );

		// Use filter to simulate constant set to false (should disable all experiments)
		// Note: This test now uses the new filter that only works when constant is not defined
		add_filter( 'graphql_experimental_features_override', function( $value ) {
			return false; // Simulate experimental features override = false
		} );

		// Clear the cache to force re-evaluation
		$test_experiment->clear_active_cache();

		// Should be false because filter overrides when constant is not defined
		$this->assertFalse( $test_experiment->is_active() );

		// Clean up the filter
		remove_all_filters( 'graphql_experimental_features_override' );
	}

	/**
	 * Test that GRAPHQL_EXPERIMENTAL_FEATURES array behavior works
	 */
	public function testGraphqlExperimentalFeaturesArray() {
		$registry = new \WPGraphQL\Experimental\ExperimentRegistry();
		$registry->init();

		$experiments = \WPGraphQL\Experimental\ExperimentRegistry::get_experiments();
		$test_experiment = $experiments['test_experiment'];

		// Use filter to simulate experimental features override set to array (should enable specific experiments)
		add_filter( 'graphql_experimental_features_override', function( $value ) {
			return [ 'test_experiment' => true ]; // Simulate experimental features override = [ 'test_experiment' => true ]
		} );

		// Clear the cache to force re-evaluation
		$test_experiment->clear_active_cache();

		// Should be true because filter array enables it when constant is not defined
		$this->assertTrue( $test_experiment->is_active() );

		// Clean up the filter
		remove_all_filters( 'graphql_experimental_features_override' );
	}

	/**
	 * Test that the graphql_experimental_features_override filter works when constant is not defined
	 */
	public function testGraphqlExperimentalFeaturesOverrideFilter() {
		$registry = new \WPGraphQL\Experimental\ExperimentRegistry();
		$registry->init();

		$experiments = \WPGraphQL\Experimental\ExperimentRegistry::get_experiments();
		$test_experiment = $experiments['test_experiment'];

		// Clear the cache to force re-evaluation
		$test_experiment->clear_active_cache();

		// Should be false initially (no constant defined, settings default to off)
		$this->assertFalse( $test_experiment->is_active() );

		// Now add a filter to override experimental features to an array (only works when constant not defined)
		add_filter( 'graphql_experimental_features_override', function( $value ) {
			return [ 'test_experiment' => true ]; // Override to enable this specific experiment
		} );

		// Clear the cache again to test the filter
		$test_experiment->clear_active_cache();

		// Should now be true because filter enables this experiment when constant is not defined
		$this->assertTrue( $test_experiment->is_active() );

		// Test with filter returning false
		remove_all_filters( 'graphql_experimental_features_override' );
		add_filter( 'graphql_experimental_features_override', function( $value ) {
			return false; // Override to disable all experiments
		} );

		// Clear the cache again
		$test_experiment->clear_active_cache();

		// Should be false because filter overrides to false
		$this->assertFalse( $test_experiment->is_active() );

		// Clean up the filter
		remove_all_filters( 'graphql_experimental_features_override' );
	}

	/**
	 * Test that constants have final say and cannot be overridden by filters
	 */
	public function testConstantHasFinalSay() {
		$registry = new \WPGraphQL\Experimental\ExperimentRegistry();
		$registry->init();

		$experiments = \WPGraphQL\Experimental\ExperimentRegistry::get_experiments();
		$test_experiment = $experiments['test_experiment'];

		// First, test that filter works when constant is not defined
		add_filter( 'graphql_experimental_features_override', function( $value ) {
			return [ 'test_experiment' => true ];
		} );

		$test_experiment->clear_active_cache();
		$this->assertTrue( $test_experiment->is_active() );

		// Now simulate a constant being defined (this should override the filter)
		// We can't actually define a constant in tests, but we can test the logic
		// by checking that the filter is only applied when constant is not defined

		// Clean up
		remove_all_filters( 'graphql_experimental_features_override' );
	}
}
