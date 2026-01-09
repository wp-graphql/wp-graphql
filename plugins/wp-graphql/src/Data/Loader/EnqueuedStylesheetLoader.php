<?php
namespace WPGraphQL\Data\Loader;

/**
 * Class EnqueuedStylesheetLoader
 *
 * @package WPGraphQL\Data\Loader
 */
class EnqueuedStylesheetLoader extends AbstractDataLoader {

	/**
	 * {@inheritDoc}
	 *
	 * @param string[] $keys Array of stylesheet handles to load
	 *
	 * @return array<string,mixed>
	 */
	public function loadKeys( array $keys ) {
		global $wp_styles;
		$loaded = [];
		foreach ( $keys as $key ) {
			if ( isset( $wp_styles->registered[ $key ] ) ) {
				$stylesheet       = $wp_styles->registered[ $key ];
				$stylesheet->type = 'EnqueuedStylesheet';
				$loaded[ $key ]   = $stylesheet;
			} else {
				$loaded[ $key ] = null;
			}
		}
		return $loaded;
	}
}
