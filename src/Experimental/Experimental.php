<?php
/**
 * Experimental features for WPGraphQL, hidden behind feature flags.
 *
 * @package WPGraphQL\Experimental
 */

namespace WPGraphQL\Experimental;

/**
 * Class - Experimental
 */
final class Experimental {
	/**
	 * Whether Experimental Functionality is enabled or not.
	 *
	 * @var ?bool
	 */
	protected $is_enabled;

	/**
	 * The Experiment Registry instance
	 *
	 * @var \WPGraphQL\Experimental\ExperimentRegistry
	 */
	protected $experiment_registry;

	/**
	 * Initializes Experimental Functionality for WPGraphQL
	 */
	public function init(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Initialize the Experiment registry.
		$this->experiment_registry = new ExperimentRegistry();
		$this->experiment_registry->init();

		// Register Admin functionality.
		if ( is_admin() ) {
			$admin = new Admin();
			$admin->init();
		}
	}

	/**
	 * Whether Experimental Functionality is enabled or not.
	 */
	protected function is_enabled(): bool {
		if ( ! isset( $this->is_enabled ) ) {
			$this->is_enabled = defined( 'GRAPHQL_EXPERIMENTAL_FEATURES' ) ? (bool) GRAPHQL_EXPERIMENTAL_FEATURES : true;
		}

		return $this->is_enabled;
	}
}
