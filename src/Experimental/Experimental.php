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

		// Initialize GraphQL Extensions functionality.
		$extensions = new Extensions();
		$extensions->init();

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
			// Check if constant is defined first
			if ( defined( 'GRAPHQL_EXPERIMENTAL_FEATURES' ) ) {
				// Constant is defined, use its value (final say)
				$this->is_enabled = (bool) GRAPHQL_EXPERIMENTAL_FEATURES;
			} else {
				// Constant not defined, apply filter with default value
				$this->is_enabled = apply_filters( 'wpgraphql_experimental_features_enabled', true );
			}
		}

		return $this->is_enabled;
	}
}
