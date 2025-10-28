<?php
/**
 * TestOptionalDependencyExperiment - Demonstrates optional dependencies
 *
 * This experiment shows how optional dependencies work. It functions
 * independently but provides enhanced functionality when its optional
 * dependencies are active.
 *
 * @package WPGraphQL\Experimental\Experiment\TestOptionalDependencyExperiment
 * @since 2.3.8
 */

namespace WPGraphQL\Experimental\Experiment\TestOptionalDependencyExperiment;

use WPGraphQL\Experimental\Experiment\AbstractExperiment;

/**
 * TestOptionalDependencyExperiment - Demonstrates optional experiment dependencies
 *
 * This experiment:
 * - Works independently (no required dependencies)
 * - Has optional dependencies that enhance its functionality
 * - Shows how to check for and use optional dependencies
 * - Demonstrates graceful degradation when optional deps are missing
 *
 * Example GraphQL query:
 * ```graphql
 * query {
 *   testOptionalDependency
 * }
 * ```
 *
 * This will return different results based on which optional dependencies are active.
 *
 * @see TestExperiment An optional dependency that enhances functionality
 * @see docs/experiments-creating.md For more examples of creating experiments
 */
class TestOptionalDependencyExperiment extends AbstractExperiment {

	/**
	 * Returns the experiment's slug.
	 */
	protected static function slug(): string {
		return 'test-optional-dependency-experiment';
	}

	/**
	 * Gets the experiment's dependencies.
	 *
	 * This experiment demonstrates optional dependencies:
	 * - Required: None (works independently)
	 * - Optional: TestExperiment (enhances functionality if available)
	 *
	 * @return array{required?:array<string>,optional?:array<string>}
	 */
	public function get_dependencies(): array {
		return [
			'required' => [],
			'optional' => [ 'test_experiment' ],
		];
	}

	/**
	 * Prepares the experiment's configuration array.
	 *
	 * @return array{title:string,description:string}
	 */
	protected function config(): array {
		return [
			'title'       => __( 'Test Optional Dependency Experiment', 'wp-graphql' ),
			'description' => __( 'A demonstration experiment that shows how optional dependencies work. This experiment functions independently but provides enhanced functionality when TestExperiment is also active.', 'wp-graphql' ),
		];
	}

	/**
	 * Gets the activation message for this experiment.
	 *
	 * @return string The activation message.
	 */
	public function get_activation_message(): string {
		return __( 'Test Optional Dependency Experiment activated! The `testOptionalDependency` field is now available in your GraphQL schema.', 'wp-graphql' );
	}

	/**
	 * Gets the deactivation message for this experiment.
	 *
	 * @return string The deactivation message.
	 */
	public function get_deactivation_message(): string {
		return __( 'Test Optional Dependency Experiment deactivated. The `testOptionalDependency` field has been removed from your GraphQL schema.', 'wp-graphql' );
	}

	/**
	 * Initializes the experiment.
	 *
	 * This method is called when the experiment is active.
	 */
	protected function init(): void {
		// Register a field that adapts based on optional dependencies
		add_action( 'graphql_register_types', [ $this, 'register_optional_dependency_field' ] );
	}

	/**
	 * Registers the testOptionalDependency field to the RootQuery type.
	 */
	public function register_optional_dependency_field(): void {
		register_graphql_field(
			'RootQuery',
			'testOptionalDependency',
			[
				'type'        => 'String',
				'description' => __( 'A test field that demonstrates optional dependencies. Returns enhanced data if TestExperiment is active, basic data otherwise.', 'wp-graphql' ),
				'resolve'     => static function () {
					// Check if our optional dependency is active
					$test_experiment_active = \WPGraphQL\Experimental\ExperimentRegistry::is_experiment_active( 'test_experiment' );

					if ( $test_experiment_active ) {
						// Enhanced functionality when optional dependency is available
						return 'Enhanced functionality: TestExperiment is active! This provides additional features.';
					} else {
						// Basic functionality when optional dependency is not available
						return 'Basic functionality: TestExperiment is not active. This still works, but with limited features.';
					}
				},
			]
		);
	}
}
