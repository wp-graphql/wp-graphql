<?php
namespace WPGraphQL\Data\Loader;

use Exception;
use WPGraphQL\Model\Taxonomy;

/**
 * Class TaxonomyLoader
 *
 * @package WPGraphQL\Data\Loader
 */
class TaxonomyLoader extends AbstractDataLoader {

	/**
	 * @param mixed $entry The User Role object
	 * @param mixed $key The Key to identify the user role by
	 *
	 * @return mixed|\WPGraphQL\Model\Taxonomy
	 * @throws \Exception
	 */
	protected function get_model( $entry, $key ) {
		return new Taxonomy( $entry );
	}

	/**
	 * @param array $keys
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function loadKeys( array $keys ) {
		$taxonomies = \WPGraphQL::get_allowed_taxonomies( 'objects' );

		$loaded = [];
		if ( ! empty( $taxonomies ) && is_array( $taxonomies ) ) {
			foreach ( $keys as $key ) {
				if ( isset( $taxonomies[ $key ] ) ) {
					$loaded[ $key ] = $taxonomies[ $key ];
				} else {
					$loaded[ $key ] = null;
				}
			}
		}

		return $loaded;
	}
}
