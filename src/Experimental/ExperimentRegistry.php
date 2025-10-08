<?php
/**
 * Registers and manages our experimental features.
 *
 * @package WPGraphQL\Experimental
 * @since next-version
 */

namespace WPGraphQL\Experimental;

use WPGraphQL\Experimental\Experiment\AbstractExperiment;
use WPGraphQL\Experimental\Experiment\TestExperiment;

/**
 * Class - ExperimentRegistry
 */
final class ExperimentRegistry {
	/**
	 * The registered experiments.
	 *
	 * @var ?array<string,class-string<\WPGraphQL\Experimental\Experiment\AbstractExperiment>>
	 */
	protected static $registry;

	/**
	 * The initialized experiments.
	 *
	 * @var ?array<string,\WPGraphQL\Experimental\Experiment\AbstractExperiment>
	 */
	protected static $experiments;

	/**
	 * The active experiments.
	 *
	 * @var ?array<string,\WPGraphQL\Experimental\Experiment\AbstractExperiment>
	 */
	protected static $active_experiments;

	/**
	 * Initializes the Experimental Functionality for WPGraphQL
	 */
	public function init(): void {
		$this->register_experiments();
		$this->load_experiments();
	}

	/**
	 * Whether the current user can manage experimental features.
	 *
	 * @uses 'graphql_experimental_features_cap' filter to determine the capability required.
	 */
	public static function can_manage_experiments(): bool {
		return current_user_can( self::capability() );
	}

	/**
	 * The capability required to turn experimental features on and off.
	 *
	 * Defaults to `manage_options`.
	 */
	public static function capability(): string {
		/**
		 * Filters the capability required to turn experimental features on and off.
		 *
		 * @param string $capability The capability required to turn experimental features on and off. Defaults to `manage_options`.
		 */
		return apply_filters( 'graphql_experimental_features_cap', 'manage_options' );
	}

	/**
	 * Checks whether the experiment is enabled.
	 *
	 * @return array<string,class-string<\WPGraphQL\Experimental\Experiment\AbstractExperiment>>
	 */
	public static function get_experiment_registry(): array {
		if ( ! isset( self::$registry ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'Registered experiments have not been set. Make sure not to call this function before the `graphql_experiments_registered` hook.', 'wp-graphql' ),
				'@since next-version'
			);

			return [];
		}

		return self::$registry;
	}

	/**
	 * Gets the list of all experiments.
	 *
	 * @return array<string,\WPGraphQL\Experimental\Experiment\AbstractExperiment>
	 */
	public static function get_experiments(): array {
		if ( ! isset( self::$experiments ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'Experiments have not been loaded. Make sure not to call this function before the `graphql_experiments_loaded` hook.', 'wp-graphql' ),
				'@since next-version'
			);

			return [];
		}

		return self::$experiments;
	}

	/**
	 * Get the list of active experiments.
	 *
	 * @return array<string,\WPGraphQL\Experimental\Experiment\AbstractExperiment>
	 */
	public static function get_active_experiments(): array {
		if ( ! isset( self::$active_experiments ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'Active experiments have not been loaded. Make sure not to call this function before the `graphql_experiments_loaded` hook.', 'wp-graphql' ),
				'@since next-version'
			);

			return [];
		}

		return self::$active_experiments;
	}

	/**
	 * Returns whether the experiment is active.
	 *
	 * @param string $experiment_name The name of the experiment.
	 */
	public static function is_experiment_active( string $experiment_name ): bool {
		$active_experiments = self::get_active_experiments();

		return isset( $active_experiments[ $experiment_name ] );
	}

	/**
	 * Registers the experiments.
	 */
	protected function register_experiments(): void {
		$registry = [
			TestExperiment::get_slug() => TestExperiment::class,
		];

		/**
		 * Filters the list of registered experiment classes.
		 *
		 * @param array<string,class-string<\WPGraphQL\Experimental\Experiment\AbstractExperiment>> $registry The list of registered experiment classes, keyed by experiment slug.
		 */
		self::$registry = apply_filters( 'graphql_experiments_registered_classes', $registry );

		/**
		 * Fires after the experiment classes have been registered.
		 *
		 * @param array<string,class-string<\WPGraphQL\Experimental\Experiment\AbstractExperiment>> $registry The list of registered experiment classes, keyed by experiment slug.
		 */
		do_action( 'graphql_experiments_registered', self::$registry );
	}

	/**
	 * Initializes the experiments.
	 */
	protected function load_experiments(): void {
		// Bail if experiments have already been initialized.
		if ( isset( self::$experiments ) && isset( self::$active_experiments ) ) {
			return;
		}

		// Initialize the experiments.
		self::$experiments        = [];
		self::$active_experiments = [];

		$experiment_classes = self::get_experiment_registry();

		foreach ( $experiment_classes as $slug => $class_name ) {
			$this->load_experiment( $slug, $class_name );
		}

		/**
		 * Fires after all the experiments have been loaded.
		 *
		 * @param array<string,\WPGraphQL\Experimental\Experiment\AbstractExperiment>|null          $experiments The list of loaded experiment classes, keyed by experiment slug.
		 * @param array<string,class-string<\WPGraphQL\Experimental\Experiment\AbstractExperiment>> $registry The list of registered experiment classes, keyed by experiment slug.
		 */
		do_action( 'graphql_experiments_loaded', self::$experiments, self::get_experiment_registry() );
	}

	/**
	 * Load a single experiment.
	 *
	 * @param string $slug       The experiment slug.
	 * @param string $class_name The fully-qualified class name for the experiment.
	 */
	protected function load_experiment( string $slug, string $class_name ): void {
		$experiment = new $class_name();

		if ( ! $experiment instanceof AbstractExperiment ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					// translators: %s is the fully-qualified class name for AbstractExperiment.
					esc_html__( 'Experiment classes must extend the %s class.', 'wp-graphql' ),
					esc_html( AbstractExperiment::class )
				),
				'0.0.1'
			);
			return;
		}

		self::$experiments[ $slug ] = $experiment;

		// Load the experiment if it is active.
		if ( $experiment->is_active() ) {
			$experiment->load();

			self::$active_experiments[ $slug ] = $experiment;
		}
	}

	/**
	 * Reload experiments (useful for testing).
	 */
	public function reload_experiments(): void {
		// Clear the cached active state for all experiments
		if ( isset( self::$experiments ) ) {
			foreach ( self::$experiments as $experiment ) {
				// Clear the cached is_active value using the public method
				$experiment->clear_active_cache();
			}
		}

		// Clear both arrays to force reload
		self::$active_experiments = [];
		self::$experiments        = [];

		// Reload experiments
		$this->load_experiments();
	}
}
