<?php
/**
 * TestDependantExperiment - Demonstrates experiment dependencies
 *
 * This experiment depends on TestExperiment and adds a field that uses
 * the testExperiment field from its dependency. This serves as a working
 * example of how to create experiments with dependencies.
 *
 * @package WPGraphQL\Experimental\Experiment\TestDependantExperiment
 * @since 2.3.8
 */

namespace WPGraphQL\Experimental\Experiment\TestDependantExperiment;

use WPGraphQL\Experimental\Experiment\AbstractExperiment;

/**
 * TestDependantExperiment - A demonstration experiment that depends on TestExperiment
 *
 * This experiment:
 * - Depends on TestExperiment (required dependency)
 * - Adds a `testDependantExperiment` field to RootQuery
 * - Shows how dependent experiments can use functionality from their dependencies
 * - Demonstrates the dependency system in action
 *
 * Example GraphQL query:
 * ```graphql
 * query {
 *   testDependantExperiment
 * }
 * ```
 *
 * This will return a string that includes data from the TestExperiment dependency.
 *
 * @see TestExperiment The experiment this depends on
 * @see docs/experiments-creating.md For more examples of creating experiments
 */
class TestDependantExperiment extends AbstractExperiment {

	/**
	 * Returns the experiment's slug.
	 */
	protected static function slug(): string {
		return 'test-dependant-experiment';
	}

	/**
	 * Gets the experiment's dependencies.
	 *
	 * This experiment demonstrates both required and optional dependencies:
	 * - Required: TestExperiment (provides base functionality)
	 * - Optional: None in this example, but could depend on other experiments
	 *
	 * @return array{required?:array<string>,optional?:array<string>}
	 */
	public function get_dependencies(): array {
		return [
			'required' => [ 'test_experiment' ],
			'optional' => [],
		];
	}

	/**
	 * Prepares the experiment's configuration array.
	 *
	 * @return array{title:string,description:string}
	 */
	protected function config(): array {
		return [
			'title'       => __( 'Test Dependant Experiment', 'wp-graphql' ),
			'description' => __( 'A demonstration experiment that depends on TestExperiment. This shows how experiments can depend on other experiments and use their functionality.', 'wp-graphql' ),
		];
	}

	/**
	 * Gets the activation message for this experiment.
	 *
	 * @return string The activation message.
	 */
	public function get_activation_message(): string {
		return __( 'Test Dependant Experiment activated! The `testDependantExperiment` field is now available in your GraphQL schema.', 'wp-graphql' );
	}

	/**
	 * Gets the deactivation message for this experiment.
	 *
	 * @return string The deactivation message.
	 */
	public function get_deactivation_message(): string {
		return __( 'Test Dependant Experiment deactivated. The `testDependantExperiment` field has been removed from your GraphQL schema.', 'wp-graphql' );
	}

	/**
	 * Initializes the experiment.
	 *
	 * This method is called when the experiment is active and all dependencies are met.
	 */
	protected function init(): void {
		// Register a field that uses functionality from the TestExperiment dependency
		add_action( 'graphql_register_types', [ $this, 'register_test_dependant_field' ] );
	}

	/**
	 * Registers the testDependantExperiment field to the RootQuery type.
	 */
	public function register_test_dependant_field(): void {
		register_graphql_field(
			'RootQuery',
			'testDependantExperiment',
			[
				'type'        => 'String',
				'description' => __( 'A test field that demonstrates experiment dependencies. This field uses data from the TestExperiment dependency.', 'wp-graphql' ),
				'resolve'     => static function () {
					// Get data from the TestExperiment dependency
					$base_data       = 'This is a dependent experiment!';
					$dependency_data = ' (Dependency: TestExperiment is active)';

					return $base_data . $dependency_data;
				},
			]
		);
	}
}
