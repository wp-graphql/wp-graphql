<?php
/**
 * Registers and manages our experimental features.
 *
 * @package WPGraphQL\Experimental
 * @since 2.3.8
 */

namespace WPGraphQL\Experimental;

use WPGraphQL\Experimental\Experiment\AbstractExperiment;
use WPGraphQL\Experimental\Experiment\EmailAddressScalarExperiment\EmailAddressScalarExperiment;
use WPGraphQL\Experimental\Experiment\EmailAddressScalarFieldsExperiment\EmailAddressScalarFieldsExperiment;

// Uncomment these imports to enable the example/test experiments:
// use WPGraphQL\Experimental\Experiment\TestDependantExperiment\TestDependantExperiment;
// use WPGraphQL\Experimental\Experiment\TestExperiment\TestExperiment;
// use WPGraphQL\Experimental\Experiment\TestOptionalDependencyExperiment\TestOptionalDependencyExperiment;

/**
 * Class - ExperimentRegistry
 */
final class ExperimentRegistry {
	/**
	 * The primary registry instance for global access.
	 *
	 * This allows static methods to work for backward compatibility
	 * while still supporting isolated instances for testing.
	 *
	 * @var ?\WPGraphQL\Experimental\ExperimentRegistry
	 */
	protected static $instance;

	/**
	 * The registered experiments.
	 *
	 * @var ?array<string,class-string<\WPGraphQL\Experimental\Experiment\AbstractExperiment>>
	 */
	protected $registry;

	/**
	 * The initialized experiments.
	 *
	 * @var ?array<string,\WPGraphQL\Experimental\Experiment\AbstractExperiment>
	 */
	protected $experiments;

	/**
	 * The active experiments.
	 *
	 * @var ?array<string,\WPGraphQL\Experimental\Experiment\AbstractExperiment>
	 */
	protected $active_experiments;


	/**
	 * Initializes the Experimental Functionality for WPGraphQL
	 *
	 * @param bool $set_as_primary Whether to set this instance as the primary (global) instance.
	 *                              Set to false for isolated test instances.
	 */
	public function init( bool $set_as_primary = true ): void {
		$this->register_experiments();
		$this->load_experiments();

		// Set this as the primary instance for global access (unless explicitly disabled for testing)
		if ( $set_as_primary ) {
			self::$instance = $this;
		}
	}

	/**
	 * Gets the primary registry instance.
	 *
	 * @return \WPGraphQL\Experimental\ExperimentRegistry|null The primary registry instance, or null if not initialized.
	 */
	public static function get_instance(): ?self {
		return self::$instance;
	}

	/**
	 * Sets the primary registry instance.
	 *
	 * Useful for testing to set a specific instance as the primary.
	 *
	 * @param \WPGraphQL\Experimental\ExperimentRegistry|null $instance The instance to set as primary.
	 */
	public static function set_instance( ?self $instance ): void {
		self::$instance = $instance;
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
	 * Gets the registry of experiment classes.
	 *
	 * @return array<string,class-string<\WPGraphQL\Experimental\Experiment\AbstractExperiment>>
	 */
	public function get_experiment_registry(): array {
		if ( ! isset( $this->registry ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'Registered experiments have not been set. Make sure not to call this function before the `graphql_experiments_registered` hook.', 'wp-graphql' ),
				'2.5.0'
			);

			return [];
		}

		return $this->registry;
	}

	/**
	 * Gets the list of all experiments.
	 *
	 * @return array<string,\WPGraphQL\Experimental\Experiment\AbstractExperiment>
	 */
	public function get_experiments(): array {
		if ( ! isset( $this->experiments ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'Experiments have not been loaded. Make sure not to call this function before the `graphql_experiments_loaded` hook.', 'wp-graphql' ),
				'2.5.0'
			);

			return [];
		}

		return $this->experiments;
	}

	/**
	 * Get the list of active experiments.
	 *
	 * @return array<string,\WPGraphQL\Experimental\Experiment\AbstractExperiment>
	 */
	public function get_active_experiments(): array {
		if ( ! isset( $this->active_experiments ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'Active experiments have not been loaded. Make sure not to call this function before the `graphql_experiments_loaded` hook.', 'wp-graphql' ),
				'2.5.0'
			);

			return [];
		}

		return $this->active_experiments;
	}

	/**
	 * Get all registered experiment classes (before instantiation).
	 *
	 * @return array<string,class-string<\WPGraphQL\Experimental\Experiment\AbstractExperiment>>
	 */
	public function get_registered_experiments(): array {
		return $this->registry ?? [];
	}

	/**
	 * Get a single registered experiment class by slug.
	 *
	 * @param string $slug The experiment slug.
	 * @return class-string<\WPGraphQL\Experimental\Experiment\AbstractExperiment>|null The experiment class name, or null if not found.
	 */
	public function get_registered_experiment( string $slug ): ?string {
		return $this->registry[ $slug ] ?? null;
	}

	/**
	 * Register a new experiment class.
	 *
	 * This method allows programmatic registration of experiments, which is useful
	 * for testing or for registering experiments after the initial registration phase.
	 *
	 * @param string                                                              $slug       The experiment slug.
	 * @param class-string<\WPGraphQL\Experimental\Experiment\AbstractExperiment> $class_name The fully-qualified class name for the experiment.
	 */
	public function register_experiment( string $slug, string $class_name ): void {
		// Initialize registry if not set
		if ( ! isset( $this->registry ) ) {
			$this->registry = [];
		}

		$this->registry[ $slug ] = $class_name;
	}

	/**
	 * Unregister an experiment by slug.
	 *
	 * This method allows programmatic removal of experiments, which is useful
	 * for testing or for removing experiments dynamically.
	 *
	 * @param string $slug The experiment slug to unregister.
	 */
	public function unregister_experiment( string $slug ): void {
		if ( isset( $this->registry[ $slug ] ) ) {
			unset( $this->registry[ $slug ] );
		}

		if ( isset( $this->experiments[ $slug ] ) ) {
			unset( $this->experiments[ $slug ] );
		}

		if ( isset( $this->active_experiments[ $slug ] ) ) {
			unset( $this->active_experiments[ $slug ] );
		}
	}

	/**
	 * Returns whether the experiment is active.
	 *
	 * @param string $experiment_name The name of the experiment.
	 */
	public function is_experiment_active( string $experiment_name ): bool {
		$active_experiments = $this->get_active_experiments();

		return isset( $active_experiments[ $experiment_name ] );
	}

	/**
	 * Registers the experiments.
	 *
	 * This is where core experiments are registered. Third-party experiments
	 * can be registered using the 'graphql_experiments_registered_classes' filter.
	 */
	protected function register_experiments(): void {
		$registry = [
			// EmailAddressScalarExperiment: Registers the EmailAddress scalar type for email validation.
			// This provides automatic validation using WordPress's is_email() and sanitize_email() functions.
			'email-address-scalar'        => EmailAddressScalarExperiment::class,

			// EmailAddressScalarFieldsExperiment: Adds emailAddress fields to core types using the EmailAddress scalar.
			// This experiment requires email-address-scalar to be active.
			'email-address-scalar-fields' => EmailAddressScalarFieldsExperiment::class,

			// ============================================================================
			// EXAMPLE EXPERIMENTS (commented out by default)
			// ============================================================================
			// The following experiments are examples that demonstrate how to create experiments.
			// Uncomment them (and their imports above) to see how they work.
			// See: src/Experimental/Experiment/TestExperiment/README.md for documentation.
			//
			// TestExperiment: A simple example that adds a testExperiment field to RootQuery.
			// 'test_experiment' => TestExperiment::class,
			//
			// TestDependantExperiment: Demonstrates required experiment dependencies.
			// This experiment requires TestExperiment and shows how required dependencies work.
			// 'test-dependant-experiment' => TestDependantExperiment::class,
			//
			// TestOptionalDependencyExperiment: Demonstrates optional experiment dependencies.
			// This experiment works independently but provides enhanced functionality when TestExperiment is active.
			// 'test-optional-dependency-experiment' => TestOptionalDependencyExperiment::class,
		];

		/**
		 * Filters the list of registered experiment classes.
		 *
		 * Use this filter to register custom experiments:
		 *
		 * ```php
		 * add_filter( 'graphql_experiments_registered_classes', function( $registry ) {
		 *     $registry['my-experiment'] = MyExperiment::class;
		 *     return $registry;
		 * } );
		 * ```
		 *
		 * @param array<string,class-string<\WPGraphQL\Experimental\Experiment\AbstractExperiment>> $registry The list of registered experiment classes, keyed by experiment slug.
		 */
		$this->registry = apply_filters( 'graphql_experiments_registered_classes', $registry );

		/**
		 * Fires after the experiment classes have been registered.
		 *
		 * @param array<string,class-string<\WPGraphQL\Experimental\Experiment\AbstractExperiment>> $registry The list of registered experiment classes, keyed by experiment slug.
		 */
		do_action( 'graphql_experiments_registered', $this->registry );
	}

	/**
	 * Initializes the experiments.
	 */
	protected function load_experiments(): void {
		// Bail if experiments have already been initialized.
		if ( isset( $this->experiments ) && isset( $this->active_experiments ) ) {
			return;
		}

		// Initialize the experiments.
		$this->experiments        = [];
		$this->active_experiments = [];

		$experiment_classes = $this->get_experiment_registry();

		foreach ( $experiment_classes as $slug => $class_name ) {
			$this->load_experiment( $slug, $class_name );
		}

		/**
		 * Fires after all the experiments have been loaded.
		 *
		 * @param array<string,\WPGraphQL\Experimental\Experiment\AbstractExperiment>|null          $experiments The list of loaded experiment classes, keyed by experiment slug.
		 * @param array<string,class-string<\WPGraphQL\Experimental\Experiment\AbstractExperiment>> $registry The list of registered experiment classes, keyed by experiment slug.
		 */
		do_action( 'graphql_experiments_loaded', $this->experiments, $this->get_experiment_registry() );
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

		$this->experiments[ $slug ] = $experiment;

		// Check if experiment can be loaded based on dependencies
		if ( $experiment->is_active() && $this->can_load_experiment( $experiment ) ) {
			$experiment->load();

			$this->active_experiments[ $slug ] = $experiment;
		}
	}

	/**
	 * Check if an experiment can be loaded based on its dependencies.
	 *
	 * @param \WPGraphQL\Experimental\Experiment\AbstractExperiment $experiment The experiment to check.
	 * @return bool True if the experiment can be loaded, false otherwise.
	 */
	protected function can_load_experiment( AbstractExperiment $experiment ): bool {
		$dependencies  = $experiment->get_dependencies();
		$required_deps = $dependencies['required'] ?? [];

		// Check if all required dependencies are active
		foreach ( $required_deps as $dep_slug ) {
			$is_dep_active = $this->is_experiment_active( $dep_slug );

			if ( ! $is_dep_active ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Reload experiments (useful for testing).
	 */
	public function reload_experiments(): void {
		// Clear the cached active state for all experiments
		if ( isset( $this->experiments ) ) {
			foreach ( $this->experiments as $experiment ) {
				// Clear the cached is_active value using the public method
				$experiment->clear_active_cache();
			}
		}

		// Set both arrays to null to force reload
		$this->active_experiments = null;
		$this->experiments        = null;

		// Reload experiments
		$this->load_experiments();
	}

	/**
	 * Reset the experiment registry (useful for testing).
	 *
	 * This clears all instance properties to ensure a clean slate between tests.
	 */
	public function reset(): void {
		$this->registry           = null;
		$this->experiments        = null;
		$this->active_experiments = null;
	}
}
