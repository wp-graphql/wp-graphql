<?php
/**
 * WordPress Dashboard functionality for Experiments.
 *
 * @package WPGraphQL\Experimental
 */

namespace WPGraphQL\Experimental;

use WPGraphQL\Experimental\Experiment\AbstractExperiment;

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

		// Hook into option updates to store activation/deactivation messages
		add_action( 'updated_option', [ $this, 'handle_option_update' ], 10, 3 );

		// Messages will be displayed automatically by WordPress settings_errors() function
	}

	/**
	 * Registers the Experiments section to the WPGraphQL Settings page.
	 */
	private function register_settings(): void {
		// Register the section.
		register_graphql_settings_section(
			self::$option_group,
			[
				'title' => __( 'Experiments 🧪', 'wp-graphql' ),
				'desc'  => sprintf(
					'<div class="notice notice-info inline"><p>%s</p></div>',
					__( 'WPGraphQL Experiments are experimental features that are under active development, with the goal of shipping them as core WPGraphQL features. They may change, break, or disappear at any time. Enable experiments to test them out and provide feedback. We recommend enabling and testing them in a development environment before enabling them in production.', 'wp-graphql' )
				),
			]
		);

		// Get all registered experiment classes and instantiate them for the admin UI
		$registered_experiments = ExperimentRegistry::get_registered_experiments();
		$experiments            = [];

		foreach ( $registered_experiments as $slug => $class_name ) {
			$experiment = new $class_name();
			if ( $experiment instanceof \WPGraphQL\Experimental\Experiment\AbstractExperiment ) {
				$experiments[ $slug ] = $experiment;
			}
		}

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

		foreach ( $experiments as $slug => $experiment ) {
			$config       = $experiment->get_config();
			$dependencies = $experiment->get_dependencies();

			// Build dependency-aware description
			$description = $this->build_experiment_description( $experiment, $config, $dependencies );

			// Check if experiment can be toggled (not blocked by missing required dependencies)
			$can_toggle = $this->can_experiment_be_toggled( $experiment, $dependencies );

			// Check the current setting value from the database
			$current_setting = get_option( 'graphql_experiments_settings', [] );
			$field_name      = $slug . '_enabled';
			$current_value   = $current_setting[ $field_name ] ?? 'off';

			// If the experiment cannot be toggled (disabled due to missing dependencies),
			// force the value to 'off' to show it as unchecked
			$display_value = $can_toggle ? $current_value : 'off';

			$toggle_fields[] = [
				'name'     => $field_name,
				'label'    => $this->build_experiment_label( $experiment, $dependencies ),
				'desc'     => $description,
				'type'     => 'checkbox', // @todo we probably want a better type callback.
				'default'  => 'off',
				'value'    => $display_value,
				'disabled' => defined( 'GRAPHQL_EXPERIMENTAL_FEATURES' ) || ! $can_toggle,
			];
		}

		// Register each field individually
		foreach ( $toggle_fields as $field ) {
			register_graphql_settings_field(
				self::$option_group,
				$field
			);
		}
	}

	/**
	 * Handle option updates and display activation/deactivation messages.
	 *
	 * This method is called when the experiments settings option is updated
	 * and detects changes in experiment activation status to display appropriate admin notices.
	 *
	 * @param string $option_name The name of the option being updated.
	 * @param mixed  $old_value The old option value.
	 * @param mixed  $new_value The new option value.
	 */
	public function handle_option_update( $option_name, $old_value, $new_value ): void {
		// Only process our experiments settings option
		if ( 'graphql_experiments_settings' !== $option_name ) {
			return;
		}

		// Check if this is coming from the experiments settings page
		$referer = wp_get_referer();

		// Check if the referer is from the graphql-settings page (which includes experiments tab)
		if ( ! $referer || false === \strpos( $referer, 'page=graphql-settings' ) ) {
			return;
		}

		// Get the old and new settings
		$old_settings = is_array( $old_value ) ? $old_value : [];
		$new_settings = is_array( $new_value ) ? $new_value : [];

		// Get all registered experiments
		$registered_experiments = ExperimentRegistry::get_registered_experiments();
		$changed_experiments    = [];

		// Check for changes in each experiment
		foreach ( $registered_experiments as $slug => $class_name ) {
			$field_name = $slug . '_enabled';
			$old_value  = $old_settings[ $field_name ] ?? 'off';
			$new_value  = $new_settings[ $field_name ] ?? 'off';

			// If the value changed, record it
			if ( $old_value !== $new_value ) {
				$experiment = new $class_name();
				if ( $experiment instanceof \WPGraphQL\Experimental\Experiment\AbstractExperiment ) {
					$changed_experiments[] = [
						'experiment' => $experiment,
						'old_value'  => $old_value,
						'new_value'  => $new_value,
					];
				}
			}
		}

		// Store messages for display on next page load
		$messages = [];
		foreach ( $changed_experiments as $change ) {
			$experiment = $change['experiment'];
			$old_value  = $change['old_value'];
			$new_value  = $change['new_value'];

			// Experiment was activated
			if ( 'off' === $old_value && 'on' === $new_value ) {
				$message = $experiment->get_activation_message();
				if ( $message ) {
					// Add README link to custom message if available
					$readme_link = $experiment->get_readme_link();
					if ( $readme_link ) {
						$message .= ' ' . $readme_link;
					}
					$messages[] = [
						'type'    => 'success',
						'message' => $message,
					];
				} else {
					// Generic activation message with README link
					$config      = $experiment->get_config();
					$title       = $config['title'] ?? $experiment->get_slug();
					$readme_link = $experiment->get_readme_link();
					$message     = sprintf( '%s experiment activated.', $title );

					if ( $readme_link ) {
						$message .= ' ' . $readme_link;
					}

					$messages[] = [
						'type'    => 'success',
						'message' => $message,
					];
				}
			}

			// Experiment was deactivated
			if ( 'on' === $old_value && 'off' === $new_value ) {
				$message = $experiment->get_deactivation_message();
				if ( $message ) {
					// Add README link to custom message if available
					$readme_link = $experiment->get_readme_link();
					if ( $readme_link ) {
						$message .= ' ' . $readme_link;
					}
					$messages[] = [
						'type'    => 'info',
						'message' => $message,
					];
				} else {
					// Generic deactivation message with README link
					$config      = $experiment->get_config();
					$title       = $config['title'] ?? $experiment->get_slug();
					$readme_link = $experiment->get_readme_link();
					$message     = sprintf( '%s experiment deactivated.', $title );

					if ( $readme_link ) {
						$message .= ' ' . $readme_link;
					}

					$messages[] = [
						'type'    => 'info',
						'message' => $message,
					];
				}
			}
		}

		// Store messages using WordPress settings errors system
		if ( ! empty( $messages ) ) {
			foreach ( $messages as $message_data ) {
				$type    = $message_data['type'];
				$message = $message_data['message'];

				// Map our types to WordPress settings error types
				$wp_type = ( 'success' === $type ) ? 'updated' : $type;

				add_settings_error( 'graphql_experiments_settings', 'experiment_' . $type, $message, $wp_type );
			}
		}
	}

	/**
	 * Build experiment label with dependency indicators.
	 *
	 * @param \WPGraphQL\Experimental\Experiment\AbstractExperiment $experiment The experiment instance.
	 * @param array<string,array<string>>                           $dependencies The experiment's dependencies.
	 * @return string The formatted label.
	 */
	private function build_experiment_label( AbstractExperiment $experiment, array $dependencies ): string {
		$config = $experiment->get_config();
		$label  = $config['title'];

		$required_deps = $dependencies['required'] ?? [];
		$optional_deps = $dependencies['optional'] ?? [];

		// Add dependency indicators
		if ( ! empty( $required_deps ) ) {
			$label .= ' 🔗'; // Required dependency indicator
		}

		if ( ! empty( $optional_deps ) ) {
			$label .= ' ✨'; // Optional dependency indicator
		}

		return $label;
	}

	/**
	 * Build experiment description with dependency information.
	 *
	 * @param \WPGraphQL\Experimental\Experiment\AbstractExperiment $experiment The experiment instance.
	 * @param array<string,string|null>                             $config The experiment configuration.
	 * @param array<string,array<string>>                           $dependencies The experiment's dependencies.
	 * @return string The formatted description.
	 */
	private function build_experiment_description( AbstractExperiment $experiment, array $config, array $dependencies ): string {
		$description   = $config['description'] ?? '';
		$required_deps = $dependencies['required'] ?? [];
		$optional_deps = $dependencies['optional'] ?? [];

		// Add dependency callouts
		if ( ! empty( $required_deps ) || ! empty( $optional_deps ) ) {
			$description .= $this->render_dependency_callouts( $required_deps, $optional_deps );
		}

		return $description;
	}

	/**
	 * Render dependency callouts inline with experiment settings.
	 *
	 * @param array<string> $required_deps Array of required dependency slugs.
	 * @param array<string> $optional_deps Array of optional dependency slugs.
	 * @return string HTML for dependency callouts.
	 */
	private function render_dependency_callouts( array $required_deps, array $optional_deps ): string {
		$callouts = '';

		// Required dependencies callout
		if ( ! empty( $required_deps ) ) {
			$required_labels       = [];
			$has_inactive_required = false;

			foreach ( $required_deps as $dep_slug ) {
				$dep_experiment = $this->get_experiment_by_slug( $dep_slug );
				$dep_config     = $dep_experiment ? $dep_experiment->get_config() : null;
				$dep_title      = $dep_config ? $dep_config['title'] : $dep_slug;
				$is_active      = $dep_experiment ? $dep_experiment->is_active() : false;

				if ( ! $is_active ) {
					$has_inactive_required = true;
				}

				$required_labels[] = $dep_title;
			}

			// Custom inline styling to avoid WordPress admin notice detection
			if ( $has_inactive_required ) {
				$callout_style = 'margin: 8px 0; padding: 8px 12px; border-left: 4px solid #dc3232; background-color: #fef7f7; border-radius: 3px; font-size: 13px;';
				$message       = sprintf(
					/* translators: %s: List of required experiment names */
					__( 'This experiment cannot be activated because required experiments are missing or inactive: %s', 'wp-graphql' ),
					implode( ', ', $required_labels )
				);
			} else {
				$callout_style = 'margin: 8px 0; padding: 8px 12px; border-left: 4px solid #00a0d2; background-color: #f7fcfe; border-radius: 3px; font-size: 13px;';
				$message       = sprintf(
					/* translators: %s: List of required experiment names */
					__( 'Required experiments: %s', 'wp-graphql' ),
					implode( ', ', $required_labels )
				);
			}

			$callouts .= sprintf(
				'<div style="%s"><p style="margin: 0; color: #333;">%s</p></div>',
				esc_attr( $callout_style ),
				esc_html( $message )
			);
		}

		// Optional dependencies callout
		if ( ! empty( $optional_deps ) ) {
			$optional_labels     = [];
			$has_active_optional = false;

			foreach ( $optional_deps as $dep_slug ) {
				$dep_experiment = $this->get_experiment_by_slug( $dep_slug );
				$dep_config     = $dep_experiment ? $dep_experiment->get_config() : null;
				$dep_title      = $dep_config ? $dep_config['title'] : $dep_slug;
				$is_active      = $dep_experiment ? $dep_experiment->is_active() : false;

				if ( $is_active ) {
					$has_active_optional = true;
				}

				$optional_labels[] = $dep_title;
			}

			// Custom inline styling for optional dependencies
			$callout_style = 'margin: 8px 0; padding: 8px 12px; border-left: 4px solid #ffb900; background-color: #fffbf0; border-radius: 3px; font-size: 13px;';

			if ( $has_active_optional ) {
				$message = sprintf(
					/* translators: %s: List of optional experiment names */
					__( 'Enhanced functionality available with: %s', 'wp-graphql' ),
					implode( ', ', $optional_labels )
				);
			} else {
				$message = sprintf(
					/* translators: %s: List of optional experiment names */
					__( 'Enhanced functionality available with: %s (not active)', 'wp-graphql' ),
					implode( ', ', $optional_labels )
				);
			}

			$callouts .= sprintf(
				'<div style="%s"><p style="margin: 0; color: #333;">%s</p></div>',
				esc_attr( $callout_style ),
				esc_html( $message )
			);
		}

		return $callouts;
	}

	/**
	 * Check if an experiment can be toggled (not blocked by missing required dependencies).
	 *
	 * @param \WPGraphQL\Experimental\Experiment\AbstractExperiment $experiment The experiment instance.
	 * @param array<string,array<string>>                           $dependencies The experiment's dependencies.
	 * @return bool True if the experiment can be toggled, false otherwise.
	 */
	private function can_experiment_be_toggled( AbstractExperiment $experiment, array $dependencies ): bool {
		$required_deps = $dependencies['required'] ?? [];

		// Check if all required dependencies are active
		foreach ( $required_deps as $dep_slug ) {
			$dep_experiment = $this->get_experiment_by_slug( $dep_slug );
			if ( ! $dep_experiment || ! $dep_experiment->is_active() ) {
				// If any required dependency is missing, the experiment cannot be toggled
				// This prevents both activation (when inactive) and keeps it disabled (when active)
				return false;
			}
		}

		return true;
	}

	/**
	 * Get an experiment by its slug.
	 *
	 * @param string $slug The experiment slug.
	 * @return \WPGraphQL\Experimental\Experiment\AbstractExperiment|null The experiment instance or null if not found.
	 */
	private function get_experiment_by_slug( string $slug ): ?AbstractExperiment {
		$experiments = ExperimentRegistry::get_experiments();
		return $experiments[ $slug ] ?? null;
	}
}
