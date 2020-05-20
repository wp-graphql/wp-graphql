<?php
namespace WPGraphQL\Data\Loader;

use WPGraphQL\Model\Theme;

/**
 * Class ThemeLoader
 *
 * @package WPGraphQL\Data\Loader
 */
class ThemeLoader extends AbstractDataLoader {

	/**
	 * @param array $keys
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function loadKeys( array $keys ) {
		$themes = wp_get_themes();
		$loaded = [];

		if ( is_array( $themes ) && ! empty( $themes ) ) {
			foreach ( $keys as $key ) {

				$loaded[ $key ] = null;

				if ( isset( $themes[ $key ] ) ) {
					$stylesheet = $themes[ $key ]->get_stylesheet();
					$theme      = wp_get_theme( $stylesheet );
					if ( $theme->exists() ) {
						$loaded[ $key ] = new Theme( $theme );
					} else {
						$loaded[ $key ] = null;
					}
				}
			}
		}

		return $loaded;
	}
}
