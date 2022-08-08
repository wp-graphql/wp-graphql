<?php
namespace WPGraphQL\Data\Connection;

use Exception;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;

/**
 * Class PluginConnectionResolver - Connects plugins to other objects
 *
 * @package WPGraphQL\Data\Resolvers
 * @since 0.0.5
 */
class PluginConnectionResolver extends AbstractConnectionResolver {
	/**
	 * {@inheritDoc}
	 *
	 * @var array
	 */
	protected $query;

	/**
	 * PluginConnectionResolver constructor.
	 *
	 * @param mixed       $source     source passed down from the resolve tree
	 * @param array       $args       array of arguments input in the field as part of the GraphQL query
	 * @param AppContext  $context    Object containing app context that gets passed down the resolve tree
	 * @param ResolveInfo $info       Info about fields passed down the resolve tree
	 *
	 * @throws Exception
	 */
	public function __construct( $source, array $args, AppContext $context, ResolveInfo $info ) {
		parent::__construct( $source, $args, $context, $info );
	}

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
	public function get_query_args() {
		if ( ! empty( $this->args['where']['status'] ) ) {
			$this->args['where']['stati'] = [ $this->args['where']['status'] ];
		} elseif ( ! empty( $this->args['where']['stati'] ) && is_string( $this->args['where']['stati'] ) ) {
			$this->args['where']['stati'] = [ $this->args['where']['stati'] ];
		}

		return $this->args;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array
	 */
	public function get_query() {
		// File has not loaded.
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		// This is missing must use and drop in plugins, so we need to fetch and merge them separately.
		$site_plugins   = apply_filters( 'all_plugins', get_plugins() );
		$mu_plugins     = apply_filters( 'show_advanced_plugins', true, 'mustuse' ) ? get_mu_plugins() : [];
		$dropin_plugins = apply_filters( 'show_advanced_plugins', true, 'dropins' ) ? get_dropins() : [];

		$all_plugins = array_merge( $site_plugins, $mu_plugins, $dropin_plugins );

		// Bail early if no plugins.
		if ( empty( $all_plugins ) ) {
			return [];
		}

		// Holds the plugin names sorted by status. The other ` status =>  [ plugin_names ] ` will be added later.
		$plugins_by_status = [
			'mustuse' => array_flip( array_keys( $mu_plugins ) ),
			'dropins' => array_flip( array_keys( $dropin_plugins ) ),
		];

		// Permissions.
		$can_update           = current_user_can( 'update_plugins' );
		$can_view_autoupdates = $can_update && function_exists( 'wp_is_auto_update_enabled_for_type' ) && wp_is_auto_update_enabled_for_type( 'plugin' );
		$show_network_plugins = apply_filters( 'show_network_active_plugins', current_user_can( 'manage_network_plugins' ) );

		// Store the plugin stati as array keys for performance.
		$active_stati = ! empty( $this->args['where']['stati'] ) ? array_flip( $this->args['where']['stati'] ) : [];

		// Get additional plugin info.
		$upgradable_list         = $can_update && isset( $active_stati['upgrade'] ) ? get_site_transient( 'update_plugins' ) : [];
		$recently_activated_list = isset( $active_stati['recently_activated'] ) ? get_site_option( 'recently_activated', [] ) : [];

		// Loop through the plugins, add additional data, and store them in $plugins_by_status.
		foreach ( (array) $all_plugins as $plugin_file => $plugin_data ) {

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
				// Exra info if known.
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

		// Filter the plugins by stati.
		$filtered_plugins = ! empty( $active_stati ) ? array_merge( ... array_values( array_intersect_key( $plugins_by_status, $active_stati ) ) ) : $plugins_by_status['all'];

		if ( ! empty( $this->args['where']['search'] ) ) {
			// Filter by search args.
			$s       = sanitize_text_field( $this->args['where']['search'] );
			$matches = array_keys(
				array_filter(
					$all_plugins,
					function ( $plugin ) use ( $s ) {
						foreach ( $plugin as $value ) {
							if ( is_string( $value ) && false !== stripos( strip_tags( $value ), $s ) ) {
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
	public function get_loader_name() {
		return 'plugin';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_valid_offset( $offset ) {
		// File has not loaded.
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		// This is missing must use and drop in plugins, so we need to fetch and merge them separately.
		$site_plugins   = apply_filters( 'all_plugins', get_plugins() );
		$mu_plugins     = apply_filters( 'show_advanced_plugins', true, 'mustuse' ) ? get_mu_plugins() : [];
		$dropin_plugins = apply_filters( 'show_advanced_plugins', true, 'dropins' ) ? get_dropins() : [];

		$all_plugins = array_merge( $site_plugins, $mu_plugins, $dropin_plugins );

		return array_key_exists( $offset, $all_plugins );
	}

	/**
	 * @return bool
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
}
