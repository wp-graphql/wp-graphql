<?php
/**
 * Handle the plugin update checks and notifications.
 *
 * @internal This class is for internal use only. It may change in the future without warning.
 *
 * Code is inspired by and adapted from WooCommerce's WC_Plugin_Updates class.
 * @see https://github.com/woocommerce/woocommerce/blob/5f04212f8188e0f7b09f6375d1a6c610fac8a631/plugins/woocommerce/includes/admin/plugin-updates/class-wc-plugin-updates.php
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
	 * The current version of the plugin.
	 *
	 * @var string
	 */
	public $current_version = WPGRAPHQL_VERSION;

	/**
	 * The new version of the available.
	 *
	 * @var string
	 */
	public $new_version;

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
		$this->release_type = SemVer::get_release_type( $this->current_version, $this->new_version );
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

		// If there are active incompatible plugins, don't allow the update.
		$incompatible_plugins = $this->get_incompatible_plugins( $this->new_version, true );

		if ( ! empty( $incompatible_plugins ) ) {
			return false;
		}

		// If allow untested autoupdates enabled, allow the update.
		if ( $this->should_allow_untested_autoupdates() ) {
			return $default_value;
		}

		$untested_release_type = $this->get_untested_release_type();
		$untested_plugins      = $this->get_untested_plugins( $untested_release_type );

		if ( ! empty( $untested_plugins ) ) {
			return false;
		}

		return $default_value;
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

		$dependents = array_merge(
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
			$tested_version = SemVer::parse( $plugin[ self::TESTED_UP_TO_HEADER ] );
			if ( null === $tested_version ) {
				continue;
			}

			// If the major version is greater, the plugin is untested.
			if ( $version['major'] > $tested_version['major'] ) {
				$untested_plugins[ $file ] = $plugin;
				continue;
			}

			// If the minor version is greater, the plugin is untested.
			if ( 'major' !== $release_type && $version['minor'] > $tested_version['minor'] ) {
				$untested_plugins[ $file ] = $plugin;
				continue;
			}

			// If the patch version is greater, the plugin is untested.
			if ( 'major' !== $release_type && 'minor' !== $release_type && $version['patch'] > $tested_version['patch'] ) {
				$untested_plugins[ $file ] = $plugin;
				continue;
			}
		}

		return $untested_plugins;
	}

	/**
	 * Get incompatible plugins.
	 *
	 * @param string $version The current plugin version.
	 * @param bool   $active_only Whether to only return active plugins. Default false.
	 *
	 * @return array<string,array<string,mixed>> The array of incompatible plugins.
	 */
	public function get_incompatible_plugins( string $version = WPGRAPHQL_VERSION, bool $active_only = false ): array {
		$dependents = $this->get_dependents();
		$plugins    = [];

		foreach ( $dependents as $file => $plugin ) {
			// Skip if the plugin is not active or is not incompatible.
			if ( ! $this->is_incompatible_dependent( $file, $version ) ) {
				continue;
			}

			// If we only want active plugins, skip if the plugin is not active.
			if ( $active_only && ! is_plugin_active( $file ) ) {
				continue;
			}

			$plugins[ $file ] = $plugin;
		}

		return $plugins;
	}

	/**
	 * Get the shared modal HTML for the update checkers.
	 *
	 * @param array<string,array<string,mixed>> $untested_plugins The untested plugins.
	 */
	public function get_untested_plugins_modal( array $untested_plugins ): string {
		$plugins = array_map(
			static function ( $plugin ) {
				return $plugin['Name'];
			},
			$untested_plugins
		);

		if ( empty( $plugins ) ) {
			return '';
		}

		ob_start();
		?>

		<div id="wp-graphql-update-modal">
			<div class="wp-graphql-update-modal__content">

				<h1><?php esc_html_e( 'Are you sure you\'re ready to update?', 'wp-graphql' ); ?></h1>

				<div class="wp-graphql-update-notice">
					<?php echo wp_kses_post( $this->get_compatibility_warning_message( $untested_plugins ) ); ?>

					<?php if ( current_user_can( 'update_plugins' ) ) : ?>
						<div class="actions">
							<a href="#" class="button button-secondary cancel"><?php esc_html_e( 'Cancel', 'wp-graphql' ); ?></a>
							<a class="button button-primary accept" href="#"><?php esc_html_e( 'Update now', 'wp-graphql' ); ?></a>
						</div>
					<?php endif ?>
				</div>
			</div>
		</div>

		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Outputs the shared modal JS for the update checkers.
	 *
	 * @todo WIP.
	 */
	public function modal_js(): void {
		?>
		<script>
			( function( $ ) {
				// Initialize thickbox.
				tb_init( '.wp-graphql-thickbox' );

				var old_tb_position = false;

				// Make the WC thickboxes look good when opened.
				$( '.wp-graphql-thickbox' ).on( 'click', function( evt ) {
					var $overlay = $( '#TB_overlay' );
					if ( ! $overlay.length ) {
						$( 'body' ).append( '<div id="TB_overlay"></div><div id="TB_window" class="wp-graphql-update-modal__container"></div>' );
					} else {
						$( '#TB_window' ).removeClass( 'thickbox-loading' ).addClass( 'wp-graphql-update-modal__container' );
					}

					// WP overrides the tb_position function. We need to use a different tb_position function than that one.
					// This is based on the original tb_position.
					if ( ! old_tb_position ) {
						old_tb_position = tb_position;
					}
					tb_position = function() {
						$( '#TB_window' ).css( { marginLeft: '-' + parseInt( ( TB_WIDTH / 2 ), 10 ) + 'px', width: TB_WIDTH + 'px' } );
						$( '#TB_window' ).css( { marginTop: '-' + parseInt( ( TB_HEIGHT / 2 ), 10 ) + 'px' } );
					};
				});

				// Reset tb_position to WP default when modal is closed.
				$( 'body' ).on( 'thickbox:removed', function() {
					if ( old_tb_position ) {
						tb_position = old_tb_position;
					}
				});
			}
		) ( jQuery );
		</script>
		<?php
	}

	/**
	 * Returns whether to allow major plugin autoupdates.
	 *
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
		return apply_filters( 'wpgraphql_enable_major_autoupdates', false, $this->new_version, $this->current_version, $this->plugin_data );
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
		return apply_filters( 'wpgraphql_enable_untested_autoupdates', $should_allow, $this->release_type, $this->new_version, $this->current_version, $this->plugin_data );
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
	 * Checks whether a dependency is incompatible with a specific version of WPGraphQL.
	 *
	 * @param string $plugin_path The plugin path used as the key in the plugins array.
	 * @param string $version     The current version to check against.
	 */
	private function is_incompatible_dependent( string $plugin_path, string $version = WPGRAPHQL_VERSION ): bool {
		$all_plugins = $this->get_all_plugins();
		$plugin_data = $all_plugins[ $plugin_path ] ?? null;

		// If the plugin doesn't have a version header, it's compatibility is unknown.
		if ( empty( $plugin_data[ self::VERSION_HEADER ] ) ) {
			return false;
		}

		// The version is incompatible if the current version is less than the required version.
		return version_compare( $version, $plugin_data[ self::VERSION_HEADER ], '<' );
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

	/**
	 * Gets the complete compatibility warning message including the plugins table and follow-up text.
	 *
	 * @param array<string,array<string,mixed>> $untested_plugins The untested plugins.
	 * @return string The formatted HTML message.
	 */
	public function get_compatibility_warning_message( array $untested_plugins ): string {
		ob_start();
		?>
		<p>
		<?php
		echo wp_kses_post(
			sprintf(
			// translators: %s: The WPGraphQL version wrapped in a strong tag.
				__(
					'The following active plugin(s) require WPGraphQL to function but have not yet declared compatibility with %s. Before updating WPGraphQL, please:',
					'wp-graphql'
				),
				// translators: %s: The WPGraphQL version.
				sprintf( '<strong>WPGraphQL v%s</strong>', $this->new_version )
			)
		);
		?>
		</p>

		<ol>
			<li>
			<?php
			echo wp_kses_post(
				sprintf(
				// translators: %s: The WPGraphQL version wrapped in a strong tag.
					__( 'Update these plugins to their latest versions that declare compatibility with %s, OR', 'wp-graphql' ),
					sprintf( '<strong>WPGraphQL v%s</strong>', $this->new_version )
				)
			);
			?>
			</li>
			<li>
			<?php
			echo wp_kses_post(
				sprintf(
				// translators: %s: The WPGraphQL version wrapped in a strong tag.
					__( 'Confirm their compatibility with %s on your staging environment.', 'wp-graphql' ),
					sprintf( '<strong>WPGraphQL v%s</strong>', $this->new_version )
				)
			);
			?>
			</li>
		</ol>

		<div class="plugin-details-table-container">
			<table class="plugin-details-table" cellspacing="0">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Plugin', 'wp-graphql' ); ?></th>
						<th><?php esc_html_e( 'WPGraphQL Tested Up To', 'wp-graphql' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $untested_plugins as $plugin ) : ?>
						<tr>
							<td><?php echo esc_html( $plugin['Name'] ); ?></td>
							<td><?php echo esc_html( $plugin[ self::TESTED_UP_TO_HEADER ] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<p><?php esc_html_e( 'For more information, review each plugin\'s changelogs or contact the plugin\'s developers.', 'wp-graphql' ); ?></p>

		<p><strong><?php esc_html_e( 'We strongly recommend creating a backup of your site before updating.', 'wp-graphql' ); ?></strong></p>
		<?php
		return (string) ob_get_clean();
	}
}
