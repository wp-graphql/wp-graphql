<?php
/**
 * Test Experiment
 *
 * A simple demonstration experiment that shows how the Experiments API works.
 * When enabled, this adds a single `testExperiment` field to the RootQuery.
 *
 * This serves as:
 * - A working example for developers learning to create experiments
 * - A test fixture for validating the Experiments API functionality
 * - A harmless way for users to try enabling/disabling experiments
 *
 * For real-world examples of creating experiments, see:
 * - docs/experiments-creating.md
 * - Example: EmailAddress scalar experiment
 *
 * @package WPGraphQL\Experimental\Experiment\TestExperiment
 * @since 2.3.8
 */

namespace WPGraphQL\Experimental\Experiment\TestExperiment;

use WPGraphQL\Experimental\Experiment\AbstractExperiment;

/**
 * Class - TestExperiment
 *
 * A simple example experiment that adds a `RootQuery.testExperiment` field.
 *
 * When enabled, you can query:
 * ```graphql
 * query {
 *   testExperiment
 * }
 * ```
 *
 * This will return: "This is a test field for the Test Experiment."
 */
class TestExperiment extends AbstractExperiment {
	/**
	 * {@inheritDoc}
	 */
	protected static function slug(): string {
		return 'test_experiment';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function config(): array {
		return [
			'title'       => __( 'Test Experiment', 'wp-graphql' ),
			'description' => __( 'A test experiment for WPGraphQL. Registers the `RootQuery.testExperiment` field to the schema.', 'wp-graphql' ),
		];
	}

	/**
	 * Gets the activation message for this experiment.
	 *
	 * @return string The activation message.
	 */
	public function get_activation_message(): string {
		return __( 'Test Experiment activated! The `testExperiment` field is now available in your GraphQL schema. This is only a test experiment to demonstrate the Experiments API.', 'wp-graphql' );
	}

	/**
	 * Gets the deactivation message for this experiment.
	 *
	 * @return string The deactivation message.
	 */
	public function get_deactivation_message(): string {
		return __( 'Test Experiment deactivated. The `testExperiment` field has been removed from your GraphQL schema.', 'wp-graphql' );
	}

	/**
	 * Initializes the experiment.
	 *
	 * I.e where you put your hooks.
	 */
	protected function init(): void {
		add_action( 'graphql_register_types', [ $this, 'register_field' ] );
	}

	/**
	 * Registers the field for the experiment.
	 */
	public function register_field(): void {
		register_graphql_field(
			'RootQuery',
			'testExperiment',
			[
				'type'        => 'String',
				'description' => 'A test field for the Test Experiment.',
				'resolve'     => static function () {
					return 'This is a test field for the Test Experiment.';
				},
			]
		);
	}
}
