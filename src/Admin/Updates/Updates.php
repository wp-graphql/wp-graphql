<?php
/**
 * Initializes Plugin Update functionality.
 *
 * @package WPGraphQL\Admin\Updates
 * @since @todo
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

		// @todo Remove.
		$this->temp_stub_update_available();
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
		if ( ! isset( $plugin->plugin ) || ! isset( $plugin->new_version ) ) {
			return $should_update;
		}

		if ( 'wp-graphql/wp-graphql.php' !== $plugin->plugin ) {
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
	 * Temporary stub to simulate an update being available.
	 *
	 * @todo Remove before committing.
	 */
	public function temp_stub_update_available(): void {
		$update_plugins = get_site_transient( 'update_plugins' );

		if ( ! $update_plugins ) {
			return;
		}

		// Prime.
		if ( empty( $update_plugins->response ) ) {
			$update_plugins->response = [];
		}

		if ( isset( $update_plugins->no_update['wp-graphql/wp-graphql.php'] ) ) {
			$update_plugins->response['wp-graphql/wp-graphql.php'] = $update_plugins->no_update['wp-graphql/wp-graphql.php'];

			unset( $update_plugins->no_update['wp-graphql/wp-graphql.php'] );
		}

		// Stub new version.
		if ( isset( $update_plugins->response['wp-graphql/wp-graphql.php'] ) ) {
			// Ensure array.
			$update_plugins->response['wp-graphql/wp-graphql.php']->new_version = '4.0.0';
		}

		set_site_transient( 'update_plugins', $update_plugins );
	}
}
