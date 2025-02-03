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
	 * {@inheritDoc}
	 *
	 * @param array<string,mixed> $entry The plugin data
	 *
	 * @return \WPGraphQL\Model\Plugin
	 * @throws \Exception
	 */
	protected function get_model( $entry, $key ) {
		return new Plugin( $entry );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string[] $keys Array of plugin names to load
	 *
	 * @return array<string,array<string,mixed>|null>
	 * @throws \Exception
	 */
	public function loadKeys( array $keys ) {
		if ( empty( $keys ) ) {
			return $keys;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			// @phpstan-ignore requireOnce.fileNotFound
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// This is missing must use and drop in plugins, so we need to fetch and merge them separately.
		$site_plugins   = apply_filters( 'all_plugins', get_plugins() );
		$mu_plugins     = apply_filters( 'show_advanced_plugins', true, 'mustuse' ) ? get_mu_plugins() : [];
		$dropin_plugins = apply_filters( 'show_advanced_plugins', true, 'dropins' ) ? get_dropins() : [];

		$plugins = array_merge( $site_plugins, $mu_plugins, $dropin_plugins );

		$loaded = [];
		if ( ! empty( $plugins ) ) {
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
