<?php
namespace WPGraphQL\Data\Loader;

use Exception;
use WPGraphQL\Model\Model;
use WPGraphQL\Model\Theme;

/**
 * Class ThemeLoader
 *
 * @package WPGraphQL\Data\Loader
 */
class ThemeLoader extends AbstractDataLoader {

	/**
	 * @param mixed $entry The User Role object
	 * @param mixed $key The Key to identify the user role by
	 *
	 * @return \WPGraphQL\Model\Model|\WPGraphQL\Model\Theme
	 * @throws \Exception
	 */
	protected function get_model( $entry, $key ) {
		return new Theme( $entry );
	}

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
