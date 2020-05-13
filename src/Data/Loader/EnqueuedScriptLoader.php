<?php
namespace WPGraphQL\Data\Loader;

/**
 * Class EnqueuedScriptLoader
 *
 * @package WPGraphQL\Data\Loader
 */
class EnqueuedScriptLoader extends AbstractDataLoader {

	/**
	 * Given an array of enqueued script handles ($keys) load the associated
	 * enqueued scripts from the $wp_scripts registry.
	 *
	 * @param array $keys
	 *
	 * @return array
	 */
	public function loadKeys( array $keys ) {
		global $wp_scripts;
		$loaded = [];
		foreach ( $keys as $key ) {
			if ( isset( $wp_scripts->registered[ $key ] ) ) {
				$loaded[ $key ] = $wp_scripts->registered[ $key ];
			} else {
				$loaded[ $key ] = null;
			}
		}
		return $loaded;
	}
}
