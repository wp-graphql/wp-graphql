<?php
namespace WPGraphQL\Data\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;

/**
 * Class PluginConnectionResolver - Connects plugins to other objects
 *
 * @package WPGraphQL\Data\Resolvers
 * @since 0.0.5
 */
class PluginConnectionResolver {

	/**
	 * Gets plugins filtered by args. 
	 * 
	 * @param array       $args    The query arguments
	 * 
	 * @return array
	 * @access public
	 */
	public static function get_plugin_objects( array $args ) {

		// File has not loaded.
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		// This is missing must use and drop in plugins.
		$plugins = apply_filters( 'all_plugins', get_plugins() );
		$mu_plugins = get_mu_plugins();
		$dropins = get_dropins();
		$all_plugins = array_merge( $plugins, $mu_plugins, $dropins );

		if ( ! empty( $args['where']['status'] ) ) {
			switch ( $args['where']['status'] ) {

				case 'active':
					$active_plugins = array();
					foreach ( (array) $plugins as $plugin_file => $plugin_data ) {
						if ( is_plugin_active( $plugin_file ) ) {
							$active_plugins[ $plugin_file ] = $plugin_data;
						}
					}
					return $active_plugins;

				case 'drop_in':
					return $dropins;

				case 'inactive':
					$inactive_plugins = array();
					foreach ( (array) $plugins as $plugin_file => $plugin_data ) {
						if ( ! is_plugin_active( $plugin_file ) ) {
							$inactive_plugins[ $plugin_file ] = $plugin_data;
						}
					}
					return $inactive_plugins;

				case 'must_use':
					return $mu_plugins;

				case 'recently_active':
					$recently_activated = get_site_option( 'recently_activated', array() );
					$recently_active_plugins = array();
					foreach ( (array) $plugins as $plugin_file => $plugin_data ) {
						if ( isset( $recently_activated[ $plugin_file ] ) ) {
							// Populate the recently activated list with plugins that have been recently activated
							$recently_active_plugins[ $plugin_file ] = $plugin_data;
						}
					}
					return $recently_active_plugins;

				case 'upgrade':
					$current = get_site_transient( 'update_plugins' );
					$upgrade_plugins = array();
					foreach ( (array) $plugins as $plugin_file => $plugin_data ) {
						if ( isset( $current->response[ $plugin_file ] ) ) {
							$upgrade_plugins[ $plugin_file ] = $plugin_data;
						}
					}
					return $upgrade_plugins;

			}
		}
		
		return $all_plugins;
	}

	/**
	 * Creates the connection for plugins
	 *
	 * @param mixed       $source  The query results
	 * @param array       $args    The query arguments
	 * @param AppContext  $context The AppContext object
	 * @param ResolveInfo $info    The ResolveInfo object
	 *
	 * @since  0.5.0
	 * @return array
	 * @access public
	 * @throws \Exception
	 */
	public static function resolve( $source, array $args, AppContext $context, ResolveInfo $info ) {

		$plugins = self::get_plugin_objects( $args );
		$plugins_array = [];
		if ( ! empty( $plugins ) && is_array( $plugins ) ) {
			foreach ( $plugins as $plugin ) {
				$plugin_object = DataSource::resolve_plugin( $plugin );
				if ( 'private' !== $plugin_object->get_visibility() ) {
					$plugins_array[] = $plugin_object;
				}
			}
		}

		$connection = Relay::connectionFromArray( $plugins_array, $args );

		$nodes = [];
		if ( ! empty( $connection['edges'] ) && is_array( $connection['edges'] ) ) {
			foreach ( $connection['edges'] as $edge ) {
				$nodes[] = ! empty( $edge['node'] ) ? $edge['node'] : null;
			}
		}
		$connection['nodes'] = ! empty( $nodes ) ? $nodes : null;

		return ! empty( $plugins_array ) ? $connection : null;

	}

}
