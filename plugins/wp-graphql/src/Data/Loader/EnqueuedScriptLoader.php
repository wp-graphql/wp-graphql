<?php
namespace WPGraphQL\Data\Loader;

/**
 * Class EnqueuedScriptLoader
 *
 * @package WPGraphQL\Data\Loader
 */
class EnqueuedScriptLoader extends AbstractDataLoader {

	/**
	 * {@inheritDoc}
	 *
	 * @param string[] $keys Array of script handles to load
	 *
	 * @return array<string,mixed>
	 */
	public function loadKeys( array $keys ) {
		/** @var \WP_Scripts $wp_scripts */
		global $wp_scripts;

		$loaded = [];
		foreach ( $keys as $key ) {
			if ( isset( $wp_scripts->registered[ $key ] ) ) {
				$script         = $wp_scripts->registered[ $key ];
				$script->type   = 'EnqueuedScript';
				$loaded[ $key ] = $script;
			} else {
				$loaded[ $key ] = null;
			}
		}
		return $loaded;
	}
}
