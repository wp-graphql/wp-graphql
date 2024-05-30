<?php
/**
 * WPGraphQL IDE Experiment
 *
 * @package WPGraphQL\Experimental\Experiment
 */

namespace WPGraphQL\Experimental\Experiment;

use WPGraphQL\Admin\Ide\Ide;

/**
 * Class - WPGraphQLIDE
 */
class WPGraphQLIDE extends AbstractExperiment {
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
			'title'       => __( 'WPGraphQL IDE', 'wp-graphql' ),
			'description' => __( 'Enable the re-vamped WPGraphQL IDE which can be opened from the admin bar on any page, and includes new themes (light/dark mode) and more.', 'wp-graphql' ),
		];
	}

	/**
	 * Initializes the experiment.
	 *
	 * i.e where you put your hooks.
	 */
	public function init(): void {
		$ide = new Ide();
		$ide->init();
	}
}
