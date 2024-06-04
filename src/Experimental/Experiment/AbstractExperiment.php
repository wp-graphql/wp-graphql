<?php
/**
 * Abstract class for creating new experiments.
 *
 * ALL experiments should extend this class.
 *
 * @package WPGraphQL\Experimental\Experiment
 * @since @todo
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
	 * @var ?array{title:string,description:string}
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
	 * @return array{title:string,description:string}
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
	 * @return array{title:string,description:string}
	 */
	public function get_config(): array {
		if ( ! isset( $this->config ) ) {
			$this->config = $this->prepare_config();
		}

		return $this->config;
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

		// See if the experiment is set via the constant.
		$is_active = defined( 'GRAPHQL_EXPERIMENTAL_FEATURES' ) && is_array( GRAPHQL_EXPERIMENTAL_FEATURES ) && isset( GRAPHQL_EXPERIMENTAL_FEATURES[ static::get_slug() ] ) ? (bool) GRAPHQL_EXPERIMENTAL_FEATURES[ static::get_slug() ] : null;

		if ( ! isset( $is_active ) ) {
			$setting_key = static::get_slug() . '_enabled';

			$is_active = 'on' === get_graphql_setting( $setting_key, 'off', Admin::$option_group );

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
	 * Prepares the configuration.
	 *
	 * @return array{title:string,description:string}
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
	}
}
