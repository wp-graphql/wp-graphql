<?php
namespace WPGraphQL\Data\Loader;

use WPGraphQL\Model\Taxonomy;

/**
 * Class TaxonomyLoader
 *
 * @package WPGraphQL\Data\Loader
 */
class TaxonomyLoader extends AbstractDataLoader {

	/**
	 * @param array $keys
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function loadKeys( array $keys ) {
		$taxonomies = get_taxonomies( [ 'show_in_graphql' => true ], 'objects' );

		$loaded = [];
		if ( ! empty( $taxonomies ) && is_array( $taxonomies ) ) {
			foreach ( $keys as $key ) {
				if ( isset( $taxonomies[ $key ] ) ) {
					$loaded[ $key ] = new Taxonomy( $taxonomies[ $key ] );
				} else {
					$loaded[ $key ] = null;
				}
			}
		}

		return $loaded;
	}
}
