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
	 * {@inheritDoc}
	 *
	 * @param mixed|\WP_Theme $entry The User Role object
	 *
	 * @return \WPGraphQL\Model\Theme
	 */
	protected function get_model( $entry, $key ) {
		return new Theme( $entry );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<int|string,?\WP_Theme>
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
						$loaded[ $key ] = $theme;
					} else {
						$loaded[ $key ] = null;
					}
				}
			}
		}

		return $loaded;
	}
}
