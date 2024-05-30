<?php
/**
 * WordPress Dashboard functionality for Experiments.
 *
 * @package WPGraphQL\Experimental
 */

namespace WPGraphQL\Experimental;

/**
 * Class - Admin
 */
class Admin {
	/**
	 * The name of the option group
	 *
	 * @var string
	 */
	public static $option_group = 'graphql_experiments_settings';

	/**
	 * Initialize Admin functionality for Experiments
	 */
	public function init(): void {
		$this->register_settings();
	}

	/**
	 * Registers the Experiments section to the WPGraphQL Settings page.
	 */
	private function register_settings(): void {
		// Register the section.
		register_graphql_settings_section(
			self::$option_group,
			[
				'title' => __( 'Experiments 🚧️', 'wp-graphql' ),
				'desc'  => __( 'WPGraphQL Experiments are experimental features that are under active development. They may change, break, or disappear at any time.', 'wp-graphql' ),
			]
		);

		$experiments = ExperimentRegistry::get_experiments();

		// If there are no experiments to register, display a message.
		if ( empty( $experiments ) ) {
			register_graphql_settings_field(
				self::$option_group,
				[
					'label' => __( 'No Experiments Available', 'wp-graphql' ),
					'desc'  => __( 'There are no experiments available at this time.', 'wp-graphql' ),
					'type'  => 'html',
				]
			);
			return;
		}

		// Register the toggle for each Experiment.
		$toggle_fields = [];

		foreach ( $experiments as $experiment ) {
			$config = $experiment->get_config();

			$toggle_fields[] = [
				'name'     => $experiment->get_slug() . '_enabled',
				'label'    => $config['title'],
				'desc'     => $config['description'],
				'type'     => 'checkbox', // @todo we probably want a better type callback.
				'default'  => 'off',
				'value'    => $experiment->is_active() ? 'on' : 'off',
				'disabled' => defined( 'GRAPHQL_EXPERIMENTAL_FEATURES' ),
			];
		}

		register_graphql_settings_fields(
			self::$option_group,
			$toggle_fields
		);
	}
}
