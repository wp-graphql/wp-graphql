<?php
namespace WPGraphQL\Data\Connection;

/**
 * Class PluginConnectionResolver - Connects plugins to other objects
 *
 * @package WPGraphQL\Data\Connection
 * @since 0.0.5
 * @extends \WPGraphQL\Data\Connection\AbstractConnectionResolver<array<string,array<string,mixed>>>
 */
class PluginConnectionResolver extends AbstractConnectionResolver {

	/**
	 * A list of all the installed plugins, keyed by their type.
	 *
	 * @var ?array{site:array<string,mixed>,mustuse:array<string,mixed>,dropins:array<string,mixed>}
	 */
	protected $all_plugins;

	/**
	 * {@inheritDoc}
	 */
	public function get_ids_from_query() {
		$ids     = [];
		$queried = ! empty( $this->query ) ? $this->query : [];

		if ( empty( $queried ) ) {
			return $ids;
		}

		foreach ( $queried as $key => $item ) {
			$ids[ $key ] = $key;
		}

		return $ids;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function prepare_query_args( array $args ): array {
		if ( ! empty( $args['where']['status'] ) ) {
			$args['where']['stati'] = [ $args['where']['status'] ];
		} elseif ( ! empty( $args['where']['stati'] ) && is_string( $args['where']['stati'] ) ) {
			$args['where']['stati'] = [ $args['where']['stati'] ];
		}

		return $args;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string,array<string,mixed>>
	 */
	protected function query( array $query_args ) {
		// Get all plugins.
		$plugins = $this->get_all_plugins();

		$all_plugins = array_merge( $plugins['site'], $plugins['mustuse'], $plugins['dropins'] );

		// Bail early if no plugins.
		if ( empty( $all_plugins ) ) {
			return [];
		}

		// Holds the plugin names sorted by status. The other ` status =>  [ plugin_names ] ` will be added later.
		$plugins_by_status = [
			'mustuse' => array_flip( array_keys( $plugins['mustuse'] ) ),
			'dropins' => array_flip( array_keys( $plugins['mustuse'] ) ),
		];

		// Permissions.
		$can_update           = current_user_can( 'update_plugins' );
		$can_view_autoupdates = $can_update && function_exists( 'wp_is_auto_update_enabled_for_type' ) && wp_is_auto_update_enabled_for_type( 'plugin' );
		$show_network_plugins = apply_filters( 'show_network_active_plugins', current_user_can( 'manage_network_plugins' ) );

		// Store the plugin stati as array keys for performance.
		$active_stati = ! empty( $query_args['where']['stati'] ) ? array_flip( $query_args['where']['stati'] ) : [];

		// Get additional plugin info.
		$upgradable_list         = $can_update && isset( $active_stati['upgrade'] ) ? get_site_transient( 'update_plugins' ) : [];
		$recently_activated_list = isset( $active_stati['recently_activated'] ) ? get_site_option( 'recently_activated', [] ) : [];

		// Loop through the plugins, add additional data, and store them in $plugins_by_status.
		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
				unset( $all_plugins[ $plugin_file ] );
				continue;
			}

			// Handle multisite plugins.
			if ( is_multisite() && is_network_only_plugin( $plugin_file ) && ! is_plugin_active( $plugin_file ) ) {

				// Check for inactive network plugins.
				if ( $show_network_plugins ) {

					// add the plugin to the network_inactive and network_inactive list since "network_inactive" are considered inactive
					$plugins_by_status['inactive'][ $plugin_file ]         = $plugin_file;
					$plugins_by_status['network_inactive'][ $plugin_file ] = $plugin_file;
				} else {
					// Unset and skip to next plugin.
					unset( $all_plugins[ $plugin_file ] );
					continue;
				}
			} elseif ( is_plugin_active_for_network( $plugin_file ) ) {
				// Check for active network plugins.
				if ( $show_network_plugins ) {
					// add the plugin to the network_activated and active list, since "network_activated" are active
					$plugins_by_status['active'][ $plugin_file ]            = $plugin_file;
					$plugins_by_status['network_activated'][ $plugin_file ] = $plugin_file;
				} else {
					// Unset and skip to next plugin.
					unset( $all_plugins[ $plugin_file ] );
					continue;
				}
			}

			// Populate active/inactive lists.
			// @todo should this include MU/Dropins?
			if ( is_plugin_active( $plugin_file ) ) {
				$plugins_by_status['active'][ $plugin_file ] = $plugin_file;
			} else {
				$plugins_by_status['inactive'][ $plugin_file ] = $plugin_file;
			}

			// Populate recently activated list.
			if ( isset( $recently_activated_list[ $plugin_file ] ) ) {
				$plugins_by_status['recently_activated'][ $plugin_file ] = $plugin_file;
			}

			// Populate paused list.
			if ( is_plugin_paused( $plugin_file ) ) {
				$plugins_by_status['paused'][ $plugin_file ] = $plugin_file;
			}

			// Get update information.
			if ( $can_update && isset( $upgradable_list->response[ $plugin_file ] ) ) {
				// An update is available.
				$plugin_data['update'] = true;
				// Extra info if known.
				$plugin_data = array_merge( (array) $upgradable_list->response[ $plugin_file ], [ 'update-supported' => true ], $plugin_data );

				// Populate upgradable list.
				$plugins_by_status['upgrade'][ $plugin_file ] = $plugin_file;
			} elseif ( isset( $upgradable_list->no_update[ $plugin_file ] ) ) {
				$plugin_data = array_merge( (array) $upgradable_list->no_update[ $plugin_file ], [ 'update-supported' => true ], $plugin_data );
			} elseif ( empty( $plugin_data['update-supported'] ) ) {
				$plugin_data['update-supported'] = false;
			}

			// Get autoupdate information.
			if ( $can_view_autoupdates ) {
				/*
				* Create the payload that's used for the auto_update_plugin filter.
				* This is the same data contained within $upgradable_list->(response|no_update) however
				* not all plugins will be contained in those keys, this avoids unexpected warnings.
				*/
				$filter_payload = [
					'id'            => $plugin_file,
					'slug'          => '',
					'plugin'        => $plugin_file,
					'new_version'   => '',
					'url'           => '',
					'package'       => '',
					'icons'         => [],
					'banners'       => [],
					'banners_rtl'   => [],
					'tested'        => '',
					'requires_php'  => '',
					'compatibility' => new \stdClass(),
				];
				$filter_payload = (object) wp_parse_args( $plugin_data, $filter_payload );

				if ( function_exists( 'wp_is_auto_update_forced_for_item' ) ) {
					$auto_update_forced                = wp_is_auto_update_forced_for_item( 'plugin', null, $filter_payload );
					$plugin_data['auto-update-forced'] = $auto_update_forced;
				}
			}

			// Save any changes to the plugin data.
			$all_plugins[ $plugin_file ] = $plugin_data;
		}

		$plugins_by_status['all'] = array_flip( array_keys( $all_plugins ) );

		/**
		 * Filters the plugins by status.
		 * */
		$filtered_plugins = ! empty( $active_stati ) ? array_values( array_intersect_key( $plugins_by_status, $active_stati ) ) : [];
		// If plugins exist for the filter, flatten and return them. Otherwise, return the full list.
		$filtered_plugins = ! empty( $filtered_plugins ) ? array_merge( [], ...$filtered_plugins ) : $plugins_by_status['all'];

		if ( ! empty( $query_args['where']['search'] ) ) {
			// Filter by search args.
			$s       = sanitize_text_field( $query_args['where']['search'] );
			$matches = array_keys(
				array_filter(
					$all_plugins,
					static function ( $plugin ) use ( $s ) {
						foreach ( $plugin as $value ) {
							if ( is_string( $value ) && false !== stripos( wp_strip_all_tags( $value ), $s ) ) {
								return true;
							}
						}

						return false;
					}
				)
			);
			if ( ! empty( $matches ) ) {
				$filtered_plugins = array_intersect_key( $filtered_plugins, array_flip( $matches ) );
			}
		}

		// Return plugin data filtered by args.
		return ! empty( $filtered_plugins ) ? array_intersect_key( $all_plugins, $filtered_plugins ) : [];
	}

	/**
	 * {@inheritDoc}
	 */
	protected function loader_name(): string {
		return 'plugin';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_valid_offset( $offset ) {
		$plugins = $this->get_all_plugins();

		$all_plugins = array_merge( $plugins['site'], $plugins['mustuse'], $plugins['dropins'] );

		return array_key_exists( $offset, $all_plugins );
	}

	/**
	 * {@inheritDoc}
	 */
	public function should_execute() {
		if ( is_multisite() ) {
			// update_, install_, and delete_ are handled above with is_super_admin().
			$menu_perms = get_site_option( 'menu_items', [] );
			if ( empty( $menu_perms['plugins'] ) && ! current_user_can( 'manage_network_plugins' ) ) {
				return false;
			}
		} elseif ( ! current_user_can( 'activate_plugins' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Gets all the installed plugins, including must use and drop in plugins.
	 *
	 * The result is cached in the ConnectionResolver instance.
	 *
	 * @return array{site:array<string,mixed>,mustuse:array<string,mixed>,dropins:array<string,mixed>}
	 */
	protected function get_all_plugins(): array {
		if ( ! isset( $this->all_plugins ) ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				// @phpstan-ignore requireOnce.fileNotFound
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			// This is missing must use and drop in plugins, so we need to fetch and merge them separately.
			$site_plugins   = apply_filters( 'all_plugins', get_plugins() );
			$mu_plugins     = apply_filters( 'show_advanced_plugins', true, 'mustuse' ) ? get_mu_plugins() : [];
			$dropin_plugins = apply_filters( 'show_advanced_plugins', true, 'dropins' ) ? get_dropins() : [];

			$this->all_plugins = [
				'site'    => is_array( $site_plugins ) ? $site_plugins : [],
				'mustuse' => $mu_plugins,
				'dropins' => $dropin_plugins,
			];
		}

		return $this->all_plugins;
	}
}
