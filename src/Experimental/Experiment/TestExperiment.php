<?php
/**
 * An Example Experiment
 *
 * @package WPGraphQL\Experimental\Experiment
 */

namespace WPGraphQL\Experimental\Experiment;

use WPGraphQL\Experimental\Experiment\AbstractExperiment;

/**
 * Class - TestExperiment
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
	 * Initializes the experiment.
	 *
	 * I.e where you put your hooks.
	 */
	public function init(): void {
		add_action( 'graphql_init', [ $this, 'register_field' ] );
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
