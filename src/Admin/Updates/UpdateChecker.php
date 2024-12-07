<?php
/**
 * Base class to handle the plugin update checks and notifications.
 *
 * @internal This class is for internal use only. It may change in the future without warning.
 *
 * @package WPGraphQL\Admin\Updates
 */

namespace WPGraphQL\Admin\Updates;

/**
 * Class UpdateChecker
 *
 * @internal This class is for internal use only. It may change in the future without warning.
 */
class UpdateChecker {
	/**
	 * The version header to check for in the plugin file.
	 */
	public const VERSION_HEADER = 'Requires WPGraphQL';

	/**
	 * The tested up to header to check for in the plugin file.
	 */
	public const TESTED_UP_TO_HEADER = 'WPGraphQL tested up to';

	/**
	 * The local cache of _all_ plugins.
	 *
	 * @var ?array<string,array<string,mixed>>
	 */
	private $all_plugins;

	/**
	 * The array of plugins that use WPGraphQL as a dependency.
	 *
	 * @var ?array<string,array<string,mixed>>
	 */
	private $dependents;

	/**
	 * The array of plugins that *maybe* use WPGraphQL as a dependency.
	 *
	 * @var ?array<string,array<string,mixed>>
	 */
	private $possible_dependents;

	/**
	 * The WPGraphQL plugin data object.
	 *
	 * @var object
	 */
	private $plugin_data;

	/**
	 * The new version of WPGraphQL available.
	 *
	 * @var string
	 */
	public $new_version;

	/**
	 * The release type of the new version of WPGraphQL.
	 *
	 * @var 'major'|'minor'|'patch'|'prerelease'|'unknown'
	 */
	private $release_type;

	/**
	 * UpdateChecker constructor.
	 *
	 * @param object $plugin_data The plugin data object from the update check.
	 */
	public function __construct( $plugin_data ) {
		$this->plugin_data  = $plugin_data;
		$this->new_version  = property_exists( $plugin_data, 'new_version' ) ? $plugin_data->new_version : '';
		$this->release_type = SemVer::get_release_type( WPGRAPHQL_VERSION, $this->new_version );
	}

	/**
	 * Checks whether any untested or incompatible WPGraphQL extensions should prevent an autoupdate.
	 *
	 * @param bool $default_value Whether to allow the update by default.
	 */
	public function should_autoupdate( bool $default_value ): bool {
		// If this is a major release, and we have those disabled, don't allow the autoupdate.
		if ( 'major' === $this->release_type && ! $this->should_allow_major_autoupdates() ) {
			return false;
		}

		// If allow untested autoupdates enabled, allow the update.
		if ( $this->should_allow_untested_autoupdates() ) {
			return $default_value;
		}

		$untested_release_type = $this->get_untested_release_type();

		$untested_plugins = $this->get_untested_plugins( $untested_release_type );

		if ( ! empty( $untested_plugins ) ) {
			return false;
		}

		return $default_value;
	}

	/**
	 * Returns whether to allow major plugin autoupdates.
	 * Defaults to false.
	 *
	 * @uses 'wpgraphql_enable_major_autoupdates' filter.
	 */
	protected function should_allow_major_autoupdates(): bool {
		/**
		 * Filter whether to allow major autoupdates.
		 *
		 * @param bool   $should_allow    Whether to allow major autoupdates. Defaults to false.
		 * @param string $new_version     The new WPGraphQL version number.
		 * @param string $current_version The current WPGraphQL version number.
		 * @param object $plugin_data     The plugin data object.
		 */
		return apply_filters( 'wpgraphql_enable_major_autoupdates', false, $this->new_version, WPGRAPHQL_VERSION, $this->plugin_data );
	}

	/**
	 * Returns whether to allow plugin autoupdates when plugin dependencies are untested and might be incompatible.
	 *
	 * @uses `wpgraphql_untested_release_type` filter to determine the release type to use when checking for untested plugins.
	 *
	 * @uses 'wpgraphql_enable_untested_autoupdates' filter.
	 */
	protected function should_allow_untested_autoupdates(): bool {
		$should_allow = $this->get_untested_release_type() !== $this->release_type;

		/**
		 * Filter whether to allow autoupdates with untested plugins.
		 *
		 * @param bool   $should_allow    Whether to allow autoupdates with untested plugins.
		 * @param string $release_type    The release type of the current version of WPGraphQL. Either 'major', 'minor', 'patch', or 'prerelease'.
		 * @param string $new_version     The new WPGraphQL version number.
		 * @param string $current_version The current WPGraphQL version number.
		 * @param object $plugin_data     The plugin data object.
		 */
		return apply_filters( 'wpgraphql_enable_untested_autoupdates', $should_allow, $this->release_type, $this->new_version, WPGRAPHQL_VERSION, $this->plugin_data );
	}

	/**
	 * Gets the release type to use when checking for untested plugins.
	 *
	 * @return 'major'|'minor'|'patch'|'prerelease' The release type to use when checking for untested plugins.
	 */
	protected function get_untested_release_type(): string {
		/**
		 * Filter the release type to use when checking for untested plugins.
		 * This is used to prevent autoupdates when a plugin is untested with the specified channel. I.e. major > minor > patch > prerelease.
		 *
		 * @param 'major'|'minor'|'patch'|'prerelease' $release_type The release type to use when checking for untested plugins. Defaults to 'major'.
		 */
		$release_type = (string) apply_filters( 'wpgraphql_untested_release_type', 'major' );

		if ( ! in_array( $release_type, [ 'major', 'minor', 'patch', 'prerelease' ], true ) ) {
			$release_type = 'major';
		}

		return $release_type;
	}

	/**
	 * Gets a list of plugins that use WPGraphQL as a dependency and are not tested with the current version of WPGraphQL.
	 *
	 * @param string $release_type The release type of the current version of WPGraphQL.
	 *
	 * @return array<string,array<string,mixed>> The array of untested plugin data.
	 * @throws \InvalidArgumentException If the WPGraphQL version is invalid.
	 */
	public function get_untested_plugins( string $release_type ): array {
		$version = SemVer::parse( $this->new_version );

		if ( null === $version ) {
			throw new \InvalidArgumentException( esc_html__( 'Invalid WPGraphQL version', 'wp-graphql' ) );
		}

		$dependents       = array_merge(
			$this->get_dependents(),
			$this->get_possible_dependents()
		);
		$untested_plugins = [];

		foreach ( $dependents as $file => $plugin ) {
			// If the plugin doesn't have a version header, it's compatibility is unknown.
			if ( empty( $plugin[ self::TESTED_UP_TO_HEADER ] ) ) {
				$plugin[ self::TESTED_UP_TO_HEADER ] = __( 'Unknown', 'wp-graphql' );

				$untested_plugins[ $file ] = $plugin;
				continue;
			}

			// Parse the tested version.
			$plugin_version = SemVer::parse( $plugin[ self::TESTED_UP_TO_HEADER ] );
			if ( null === $plugin_version ) {
				continue;
			}

			// If the major version is greater, the plugin is untested.
			if ( $version['major'] <=> $plugin_version['major'] ) {
				$untested_plugins[ $file ] = $plugin;
				continue;
			}

			// If the minor version is greater, the plugin is untested.
			if ( 'major' !== $release_type && $version['minor'] <=> $plugin_version['minor'] ) {
				$untested_plugins[ $file ] = $plugin;
				continue;
			}

			// If the patch version is greater, the plugin is untested.
			if ( 'major' !== $release_type && 'minor' !== $release_type && $version['patch'] <=> $plugin_version['patch'] ) {
				$untested_plugins[ $file ] = $plugin;
				continue;
			}
		}

		return $untested_plugins;
	}

	/**
	 * Gets the plugins that use WPGraphQL as a dependency.
	 *
	 * @return array<string,array<string,mixed>> The array of plugins that use WPGraphQL as a dependency, keyed by plugin path.
	 */
	protected function get_dependents(): array {
		if ( isset( $this->dependents ) ) {
			return $this->dependents;
		}

		$all_plugins = $this->get_all_plugins();
		$plugins     = [];

		foreach ( $all_plugins as $plugin_path => $plugin ) {
			// If they're explicitly using a header, it's a dependent.
			if ( ! $this->is_versioned_dependent( $plugin_path ) && ! $this->is_wpapi_dependent( $plugin_path ) ) {
				continue;
			}

			$plugins[ $plugin_path ] = $plugin;
		}

		/**
		 * Filters the list of plugins that use WPGraphQL as a dependency.
		 *
		 * @param array<string,array<string,mixed>> $plugins The array of plugins that use WPGraphQL as a dependency.
		 * @param array<string,array<string,mixed>> $all_plugins The array of all plugins.
		 */
		$this->dependents = apply_filters( 'graphql_get_dependents', $plugins, $all_plugins );

		return $this->dependents;
	}

	/**
	 * Gets the plugins that *maybe* use WPGraphQL as a dependency.
	 *
	 * @return array<string,array<string,mixed>> The array of plugins that maybe use WPGraphQL as a dependency, keyed by plugin path.
	 */
	protected function get_possible_dependents(): array {
		// Bail early if we've already fetched the possible plugins.
		if ( isset( $this->possible_dependents ) ) {
			return $this->possible_dependents;
		}

		$all_plugins = $this->get_all_plugins();
		$plugins     = [];

		foreach ( $all_plugins as $plugin_path => $plugin ) {
			// Skip the WPGraphQL plugin.
			if ( 'WPGraphQL' === $plugin['Name'] ) {
				continue;
			}

			if ( ! $this->is_possible_dependent( $plugin_path ) ) {
				continue;
			}

			$plugins[ $plugin_path ] = $plugin;
		}

		/**
		 * Filters the list of plugins that use WPGraphQL as a dependency.
		 *
		 * Can be used to hide false positives or to add additional plugins that may use WPGraphQL as a dependency.
		 *
		 * @param array<string,array<string,mixed>> $plugins The array of plugins that maybe use WPGraphQL as a dependency.
		 * @param array<string,array<string,mixed>> $all_plugins The array of all plugins.
		 */
		$this->possible_dependents = apply_filters( 'graphql_get_possible_dependents', $plugins, $all_plugins );

		return $this->possible_dependents;
	}

	/**
	 * Gets all plugins, priming the cache if necessary.
	 *
	 * @return array<string,array<string,mixed>> The array of all plugins, keyed by plugin path.
	 */
	private function get_all_plugins(): array {
		if ( ! isset( $this->all_plugins ) ) {
			$this->all_plugins = get_plugins();
		}

		return $this->all_plugins;
	}

	/**
	 * Checks whether the plugin is "possibly" using WPGraphQL as a dependency.
	 *
	 * I.e if it's in the plugin name or description.
	 *
	 * @param string $plugin_path The plugin path used as the key in the plugins array.
	 */
	private function is_possible_dependent( string $plugin_path ): bool {
		$all_plugins = $this->get_all_plugins();
		$plugin_data = $all_plugins[ $plugin_path ] ?? null;

		// Bail early if the plugin doesn't exist.
		if ( empty( $plugin_data ) ) {
			return false;
		}

		return stristr( $plugin_data['Name'], 'WPGraphQL' ) || stristr( $plugin_data['Description'], 'WPGraphQL' );
	}

	/**
	 * Checks whether the plugin uses our version headers.
	 *
	 * @param string $plugin_path The plugin path used as the key in the plugins array.
	 */
	private function is_versioned_dependent( string $plugin_path ): bool {
		$all_plugins = $this->get_all_plugins();
		$plugin_data = $all_plugins[ $plugin_path ] ?? null;

		// Bail early if the plugin doesn't exist.
		if ( empty( $plugin_data ) ) {
			return false;
		}

		return ! empty( $plugin_data[ self::VERSION_HEADER ] ) || ! empty( $plugin_data[ self::TESTED_UP_TO_HEADER ] );
	}

	/**
	 * Whether the plugin lists WPGraphQL in its `Requires Plugins` header.
	 *
	 * @param string $plugin_path The plugin path used as the key in the plugins array.
	 */
	private function is_wpapi_dependent( string $plugin_path ): bool {
		$all_plugins = $this->get_all_plugins();

		$plugin_data = $all_plugins[ $plugin_path ] ?? null;

		// Bail early if the plugin doesn't exist.
		if ( empty( $plugin_data ) || empty( $plugin_data['RequiresPlugins'] ) ) {
			return false;
		}

		// Normalize the required plugins.
		$required_plugins = array_map(
			static function ( $slug ) {
				return strtolower( trim( $slug ) );
			},
			explode( ',', $plugin_data['RequiresPlugins'] ) ?: []
		);

		return in_array( 'wp-graphql', $required_plugins, true );
	}
}
