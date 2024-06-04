<?php
/**
 * WPGraphQL IDE Experiment
 *
 * @package WPGraphQL\Experimental\Experiment
 */

namespace WPGraphQL\Experimental\Experiment\WPGraphQLIDE;

use WPGraphQL\Experimental\Experiment\AbstractExperiment;

/**
 * Class - WPGraphQLIDE
 */
class InitExperiment extends AbstractExperiment {
	/**
	 * {@inheritDoc}
	 */
	protected static function slug(): string {
		return 'wpgraphql_ide';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function config(): array {
		return [
			'title'              => __( 'WPGraphQL IDE', 'wp-graphql' ),
			'description'        => __( 'Enable the re-vamped WPGraphQL IDE which can be opened from the admin bar on any page, and includes new themes (light/dark mode) and more.', 'wp-graphql' ),
			'deprecationMessage' => 'Yo!',
		];
	}

	/**
	 * Initializes the experiment.
	 *
	 * I.E. where you put your hooks.
	 */
	public function init(): void {
		$ide = new WPGraphQLIDE();
		$ide->init();
	}
}
