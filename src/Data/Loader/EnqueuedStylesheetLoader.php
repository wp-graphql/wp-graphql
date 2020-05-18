<?php
namespace WPGraphQL\Data\Loader;

/**
 * Class EnqueuedStylesheetLoader
 *
 * @package WPGraphQL\Data\Loader
 */
class EnqueuedStylesheetLoader extends AbstractDataLoader {

	/**
	 * Given an array of enqueued stylesheet handles ($keys) load the associated
	 * enqueued stylesheets from the $wp_styles registry.
	 *
	 * @param array $keys
	 *
	 * @return array
	 */
	public function loadKeys( array $keys ) {
		global $wp_styles;
		$loaded = [];
		foreach ( $keys as $key ) {
			if ( isset( $wp_styles->registered[ $key ] ) ) {
				$loaded[ $key ] = $wp_styles->registered[ $key ];
			} else {
				$loaded[ $key ] = null;
			}
		}
		return $loaded;
	}
}
