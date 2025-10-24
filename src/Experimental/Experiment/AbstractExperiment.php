<?php
/**
 * Abstract class for creating new experiments.
 *
 * ALL experiments should extend this class.
 *
 * @package WPGraphQL\Experimental\Experiment
 * @since 2.3.8
 */

namespace WPGraphQL\Experimental\Experiment;

use WPGraphQL\Experimental\Admin;

/**
 * Class - Abstract Experiment
 */
abstract class AbstractExperiment {
	/**
	 * The experiment unique slug.
	 *
	 * @var ?string
	 */
	protected static $slug;

	/**
	 * The experiment's configuration.
	 *
	 * @var ?array{title:string,description:string,deprecationMessage?:?string}
	 */
	protected $config;

	/**
	 * Whether the experiment is active.
	 *
	 * @var ?bool
	 */
	protected $is_active;

	/**
	 * Defines the experiment slug.
	 */
	abstract protected static function slug(): string;

	/**
	 * Defines the experiment configuration.
	 *
	 * @return array{title:string,description:string,deprecationMessage?:?string}
	 */
	abstract protected function config(): array;

	/**
	 * Initializes the experiment.
	 *
	 * I.e where you put your hooks.
	 */
	abstract protected function init(): void;

	/**
	 * Loads the experiment.
	 *
	 * @uses AbstractExperiment::init() to initialize the experiment.
	 */
	public function load(): void {
		if ( ! $this->is_active() ) {
			return;
		}

		$this->init();

		// Log a warning if the experiment is deprecated.
		$deprecated_message = $this->get_deprecation_message();
		if ( ! empty( $deprecated_message ) ) {
			graphql_debug(
				sprintf(
				// translators: %1$s: The experiment's slug, %2$s: The deprecation message.
					__( 'The experiment %1$s is deprecated: %2$s', 'wp-graphql' ),
					esc_html( static::get_slug() ),
					esc_html( $deprecated_message )
				)
			);
		}

		/**
		 * Fires after the experiment is loaded.
		 *
		 * @param \WPGraphQL\Experimental\Experiment\AbstractExperiment $instance The experiment instance.
		 */
		do_action( 'wp_graphql_experiment_' . $this->get_slug() . '_loaded', $this );
	}

	/**
	 * Gets the experiment's configuration array.
	 *
	 * @return array{title:string,description:string,deprecationMessage?:?string}
	 */
	public function get_config(): array {
		if ( ! isset( $this->config ) ) {
			$this->config = $this->prepare_config();
		}

		return $this->config;
	}

	/**
	 * Gets the experiment's dependencies.
	 *
	 * Override this method to specify dependencies for your experiment.
	 *
	 * @return array{required?:array<string>,optional?:array<string>} Array of dependencies.
	 *         - 'required': Array of experiment slugs that must be active
	 *         - 'optional': Array of experiment slugs that are recommended but not required
	 *
	 * @since 2.3.8
	 */
	public function get_dependencies(): array {
		return [
			'required' => [],
			'optional' => [],
		];
	}

	/**
	 * Gets the path to the experiment's README.md file.
	 *
	 * @return string|null The absolute path to the README.md file, or null if it doesn't exist.
	 *
	 * @since 2.3.8
	 */
	public function get_readme_path(): ?string {
		$reflection = new \ReflectionClass( static::class );
		$class_file = $reflection->getFileName();

		if ( false === $class_file ) {
			return null;
		}

		$class_dir   = dirname( $class_file );
		$readme_path = $class_dir . '/README.md';

		if ( file_exists( $readme_path ) ) {
			return $readme_path;
		}

		// Log a debug warning if README is missing
		graphql_debug(
			sprintf(
				// translators: %1$s: The experiment's slug, %2$s: The expected README path.
				__( 'Experiment "%1$s" is missing a README.md file. Consider adding documentation at %2$s', 'wp-graphql' ),
				static::get_slug(),
				$readme_path
			),
			[
				'experiment'    => static::get_slug(),
				'expected_path' => $readme_path,
			]
		);

		return null;
	}

	/**
	 * Gets a link to view the experiment's README.
	 *
	 * @return string|null A markdown/HTML formatted link to the README, or null if README doesn't exist.
	 *
	 * @since 2.3.8
	 */
	public function get_readme_link(): ?string {
		$readme_path = $this->get_readme_path();

		if ( null === $readme_path ) {
			return null;
		}

		// Get the relative path from the plugin root
		$plugin_root   = dirname( dirname( dirname( __DIR__ ) ) );
		$relative_path = str_replace( $plugin_root . '/', '', $readme_path );

		// Create a GitHub link if available, or a file path reference
		$github_base = 'https://github.com/wp-graphql/wp-graphql/blob/develop/';
		$readme_url  = $github_base . $relative_path;

		return sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( $readme_url ),
			__( 'View documentation', 'wp-graphql' )
		);
	}

	/**
	 * Gets the activation message for this experiment.
	 *
	 * Override this method to provide a custom activation message.
	 * This message will be displayed as an admin notice when the experiment is activated.
	 *
	 * @return string|null The activation message, or null for no message.
	 *
	 * @since 2.3.8
	 */
	public function get_activation_message(): ?string {
		return null;
	}

	/**
	 * Gets the deactivation message for this experiment.
	 *
	 * Override this method to provide a custom deactivation message.
	 * This message will be displayed as an admin notice when the experiment is deactivated.
	 *
	 * @return string|null The deactivation message, or null for no message.
	 *
	 * @since 2.3.8
	 */
	public function get_deactivation_message(): ?string {
		return null;
	}

	/**
	 * Returns the experiment's slug.
	 *
	 * This is static so it can be accessed outside of the class instantiation.
	 *
	 * @throws \Exception If the experiment is missing a slug.
	 */
	public static function get_slug(): string {
		if ( ! isset( static::$slug ) ) {
			$slug = static::slug();

			if ( empty( $slug ) ) {
				throw new \Exception(
					sprintf(
						/* translators: %s: The experiment's class name. */
						esc_html__( 'The experiment %s is missing a slug. Ensure a valid `slug` is defined in the ::slug() method.', 'wp-graphql' ),
						static::class
					)
				);
			}

			static::$slug = $slug;
		}

		return static::$slug;
	}

	/**
	 * Whether the experiment is active.
	 */
	public function is_active(): bool {
		if ( isset( $this->is_active ) ) {
			return $this->is_active;
		}

		// Check if constant is defined first (constant has final say)
		if ( defined( 'GRAPHQL_EXPERIMENTAL_FEATURES' ) ) {
			$experimental_features = GRAPHQL_EXPERIMENTAL_FEATURES;

			// See if the experiment is set via the constant
			if ( false === $experimental_features ) {
				// If value is false, disable all experiments
				$is_active = false;
			} elseif ( is_array( $experimental_features ) && isset( $experimental_features[ static::get_slug() ] ) ) {
				// If value is array, check for specific experiment
				$is_active = (bool) $experimental_features[ static::get_slug() ];
			} else {
				// If value is true or other value, fall through to settings
				$is_active = null;
			}
		} else {
			// Constant not defined, apply filter to allow programmatic control
			$experimental_features = apply_filters( 'graphql_experimental_features_override', null );

			if ( null !== $experimental_features ) {
				if ( false === $experimental_features ) {
					// If filtered value is false, disable all experiments
					$is_active = false;
				} elseif ( is_array( $experimental_features ) && isset( $experimental_features[ static::get_slug() ] ) ) {
					// If filtered value is array, check for specific experiment
					$is_active = (bool) $experimental_features[ static::get_slug() ];
				} else {
					// If filtered value is true or other value, fall through to settings
					$is_active = null;
				}
			} else {
				$is_active = null;
			}
		}

		if ( ! isset( $is_active ) ) {
			// Use the slug() method directly to avoid static caching issues
			$slug        = static::slug();
			$setting_key = $slug . '_enabled';

			$setting_value = get_graphql_setting( $setting_key, 'off', Admin::$option_group );
			$is_active     = 'on' === $setting_value;

			/**
			 * Filters whether the experiment is active.
			 *
			 * @param bool   $is_active Whether the experiment is active.
			 * @param string $slug      The experiment's slug.
			 */
			$is_active = apply_filters( 'wp_graphql_experiment_enabled', $is_active, static::get_slug() );
			$is_active = apply_filters( 'wp_graphql_experiment_' . static::get_slug() . '_enabled', $is_active );
		}

		$this->is_active = $is_active;

		return $this->is_active;
	}

	/**
	 * Gets the deprecation message, if it exists.
	 */
	public function get_deprecation_message(): ?string {
		$config = $this->get_config();

		return isset( $config['deprecationMessage'] ) ? $config['deprecationMessage'] : null;
	}

	/**
	 * Checks whether the experiment has been deprecated.
	 */
	public function is_deprecated(): bool {
		return ! empty( $this->get_deprecation_message() );
	}

	/**
	 * Prepares the configuration.
	 *
	 * @return array{title:string,description:string,deprecationMessage?:?string}
	 *
	 * @throws \Exception If the experiment is missing a slug.
	 */
	protected function prepare_config(): array {
		$slug   = static::get_slug();
		$config = $this->config();

		/**
		 * Filters the experiment configuration.
		 *
		 * @param array{title:string,description:string} $config The experiment configuration.
		 * @param string              $slug   The experiment's slug.
		 */
		$config = apply_filters( 'wp_graphql_experiment_config', $config, $slug );
		$config = apply_filters( 'wp_graphql_experiment_' . $slug . '_config', $config );

		// Validate the config.
		$this->validate_config( $config );

		return $config;
	}

	/**
	 * Validates the $config array, throwing an exception if it's invalid.
	 *
	 * @param array<string,mixed> $config The experiment configuration.
	 *
	 * @throws \Exception If the config is invalid.
	 */
	protected function validate_config( array $config ): void {
		if ( empty( $config ) ) {
			throw new \Exception(
				sprintf(
					/* translators: %s: The experiment's class name. */
					esc_html__( 'The experiment %s is missing a configuration. Ensure a valid `config` is defined in the ::config() method.', 'wp-graphql' ),
					static::class
				)
			);
		}

		if ( ! isset( $config['title'] ) || ! is_string( $config['title'] ) ) {
			throw new \Exception(
				sprintf(
					/* translators: %s: The experiment's class name. */
					esc_html__( 'The experiment %s is missing a title in the configuration. Ensure a valid `title` is defined in the ::config() method.', 'wp-graphql' ),
					static::class
				)
			);
		}

		if ( ! isset( $config['description'] ) || ! is_string( $config['description'] ) ) {
			throw new \Exception(
				sprintf(
					/* translators: %s: The experiment's class name. */
					esc_html__( 'The experiment %s is missing a description in the configuration. Ensure a valid `description` is defined in the ::config() method.', 'wp-graphql' ),
					static::class
				)
			);
		}

		if ( isset( $config['deprecationMessage'] ) && ( ! is_string( $config['deprecationMessage'] ) || empty( $config['deprecationMessage'] ) ) ) {
			throw new \Exception(
				sprintf(
					/* translators: %s: The experiment's class name. */
					esc_html__( 'The experiment %s has an invalid deprecation message in the configuration. If you are trying to deprecate the Experiment, ensure a valid `deprecationMessage` is defined in the ::config() method. Otherwise remove the `deprecationMessage` from the array', 'wp-graphql' ),
					static::class
				)
			);
		}
	}

	/**
	 * Clear the cached active state (useful for testing).
	 */
	public function clear_active_cache(): void {
		unset( $this->is_active );
	}
}
