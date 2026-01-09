<?php
/**
 * Initializes Plugin Update functionality.
 *
 * @package WPGraphQL\Admin\Updates
 * @since 1.30.0
 */

namespace WPGraphQL\Admin\Updates;

/**
 * Class Updates
 */
final class Updates {
	/**
	 * Initialize the Updates functionality.
	 */
	public function init(): void {
		// Expose the plugin headers.
		add_filter( 'extra_plugin_headers', [ $this, 'enable_plugin_headers' ] );
		add_filter( 'extra_theme_headers', [ $this, 'enable_plugin_headers' ] );

		// Prevent autoupdates for untested WPGraphQL Extensions.
		add_filter( 'auto_update_plugin', [ $this, 'maybe_allow_autoupdates' ], 10, 2 );

		// Load the Update Checker for the current screen.
		add_action( 'current_screen', [ $this, 'load_screen_checker' ] );

		// Disable incompatible plugins.
		add_action( 'admin_init', [ $this, 'disable_incompatible_plugins' ] );
		add_action( 'graphql_activate', [ $this, 'disable_incompatible_plugins' ] );
		add_action( 'admin_notices', [ $this, 'disable_incompatible_plugins_notice' ] );

		// Register admin assets.
		add_action( 'admin_enqueue_scripts', [ $this, 'register_assets' ] );
	}

	/**
	 * Enable the plugin headers.
	 *
	 * @param string[] $headers The headers.
	 * @return string[]
	 */
	public function enable_plugin_headers( $headers ) {
		$headers[] = UpdateChecker::VERSION_HEADER;
		$headers[] = UpdateChecker::TESTED_UP_TO_HEADER;

		return $headers;
	}

	/**
	 * Prevent autoupdates when there is an untested WPGraphQL Extension.
	 *
	 * @param bool   $should_update Whether the plugin should autoupdate.
	 * @param object $plugin The plugin data object.
	 *
	 * @return bool Whether the plugin should autoupdate.
	 */
	public function maybe_allow_autoupdates( $should_update, $plugin ) {
		// Bail if it's not our plugin.
		if ( ! isset( $plugin->plugin ) || ! isset( $plugin->new_version ) || 'wp-graphql/wp-graphql.php' !== $plugin->plugin ) {
			return $should_update;
		}

		// If autoupdates are already disabled we don't need to check further.
		if ( false === $should_update ) {
			return $should_update;
		}

		$new_version = sanitize_text_field( $plugin->new_version );

		if ( '' === $new_version ) {
			return $should_update;
		}

		// Store the sanitized version in the plugin object.
		$plugin->new_version = $new_version;

		$plugin_updates = new UpdateChecker( $plugin );

		return $plugin_updates->should_autoupdate( (bool) $should_update );
	}

	/**
	 * Maybe loads the Update Checker for the current admin screen.
	 */
	public function load_screen_checker(): void {
		$screen = get_current_screen();

		// Bail if we're not on a screen.
		if ( ! $screen ) {
			return;
		}

		// Loaders for the different WPAdmin Screens.
		$loaders = [
			'plugins'     => PluginsScreenLoader::class,
			'update-core' => UpdatesScreenLoader::class,
		];

		// Bail if the current screen doesn't need an update checker.
		if ( ! in_array( $screen->id, array_keys( $loaders ), true ) ) {
			return;
		}

		// Load the update checker for the current screen.
		new $loaders[ $screen->id ]();
	}

	/**
	 * Registers the admin assets.
	 */
	public function register_assets(): void {
		$screen          = get_current_screen();
		$allowed_screens = [
			'plugins',
			'update-core',
		];

		// Bail if we're not on a screen.
		if ( ! $screen || ! in_array( $screen->id, $allowed_screens, true ) ) {
			return;
		}

		$asset_file = include WPGRAPHQL_PLUGIN_DIR . 'build/updates.asset.php';

		wp_enqueue_style(
			'wp-graphql-admin-updates',
			WPGRAPHQL_PLUGIN_URL . 'build/updates.css',
			$asset_file['dependencies'],
			$asset_file['version']
		);
	}

	/**
	 * Disables plugins that don't meet the minimum `Requires WPGraphQL` version.
	 */
	public function disable_incompatible_plugins(): void {

		// Get the plugin data.
		$plugin_data = get_plugin_data( WPGRAPHQL_PLUGIN_DIR . '/wp-graphql.php' );

		// Initialize the Update Checker.
		$update_checker = new UpdateChecker( (object) $plugin_data );

		// Get the incompatible plugins.
		$incompatible_plugins = $update_checker->get_incompatible_plugins( WPGRAPHQL_VERSION, true );

		// Deactivate the incompatible plugins.
		$notice_data = [];
		foreach ( $incompatible_plugins as $file => $plugin ) {
			$notice_data[] = [
				'name'    => $plugin['Name'],
				'version' => $plugin[ UpdateChecker::VERSION_HEADER ],
			];
			deactivate_plugins( $file );
		}

		// Display a notice to the user.
		if ( ! empty( $notice_data ) ) {
			set_transient( 'wpgraphql_incompatible_plugins', $notice_data );
		}
	}

	/**
	 * Displays a one-time notice to the user if incompatible plugins were deactivated.
	 */
	public function disable_incompatible_plugins_notice(): void {
		$incompatible_plugins = get_transient( 'wpgraphql_incompatible_plugins' );

		if ( empty( $incompatible_plugins ) ) {
			return;
		}

		$notice = sprintf(
			'<p>%s</p>',
			__( 'The following plugins were deactivated because they require a newer version of WPGraphQL. Please update WPGraphQL to a newer version to reactivate these plugins.', 'wp-graphql' )
		);

		$notice .= '<ul class="ul-disc">';
		foreach ( $incompatible_plugins as $plugin ) {
			$notice .= sprintf(
				'<li><strong>%s</strong> (requires at least WPGraphQL: v%s)</li>',
				esc_html( $plugin['name'] ),
				esc_html( $plugin['version'] )
			);
		}
		$notice .= '</ul>';

		echo wp_kses_post( sprintf( '<div class="notice notice-error is-dismissable">%s</div>', $notice ) );

		// Delete once the notice is displayed.
		delete_transient( 'wpgraphql_incompatible_plugins' );
	}
}
