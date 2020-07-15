<?php

namespace WPGraphQL\Data\Loader;
use WPGraphQL\Model\Plugin;

/**
 * Class PluginLoader
 *
 * @package WPGraphQL\Data\Loader
 */
class PluginLoader extends AbstractDataLoader {

	/**
	 * @param $entry
	 * @param $key
	 *
	 * @return \WPGraphQL\Model\Model|Plugin
	 * @throws \Exception
	 */
	protected function get_model( $entry, $key ) {
		return new Plugin( $entry );
	}

	/**
	 * Given an array of plugin names, load the associated plugins from the plugin registry.
	 *
	 * @param array $keys
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function loadKeys( array $keys ) {

		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		$plugins = apply_filters( 'all_plugins', get_plugins() );

		$loaded = [];
		if ( ! empty( $plugins ) && is_array( $plugins ) ) {
			foreach ( $keys as $key ) {
				if ( isset( $plugins[ $key ] ) ) {
					$plugin         = $plugins[ $key ];
					$plugin['Path'] = $key;
					$loaded[ $key ] = $plugin;
				} else {
					$loaded[ $key ] = null;
				}
			}
		}

		return $loaded;

	}
}
