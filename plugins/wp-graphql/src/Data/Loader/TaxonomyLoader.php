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
	 * {@inheritDoc}
	 *
	 * @param mixed|\WP_Taxonomy $entry The Taxonomy Object
	 *
	 * @return \WPGraphQL\Model\Taxonomy
	 */
	protected function get_model( $entry, $key ) {
		return new Taxonomy( $entry );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string[] $keys
	 *
	 * @return array<string,\WP_Taxonomy|null>
	 */
	public function loadKeys( array $keys ) {
		$taxonomies = \WPGraphQL::get_allowed_taxonomies( 'objects' );

		if ( empty( $taxonomies ) ) {
			return [];
		}

		$loaded = [];
		foreach ( $keys as $key ) {
			if ( isset( $taxonomies[ $key ] ) ) {
				$loaded[ $key ] = $taxonomies[ $key ];
			} else {
				$loaded[ $key ] = null;
			}
		}

		return $loaded;
	}
}
