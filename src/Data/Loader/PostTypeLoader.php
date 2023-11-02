<?php
namespace WPGraphQL\Data\Loader;

use WPGraphQL\Model\PostType;

/**
 * Class PostTypeLoader
 *
 * @package WPGraphQL\Data\Loader
 */
class PostTypeLoader extends AbstractDataLoader {

	/**
	 * {@inheritDoc}
	 *
	 * @param mixed|\WP_Post_Type $entry The Post Type Object
	 *
	 * @return \WPGraphQL\Model\PostType
	 */
	protected function get_model( $entry, $key ) {
		return new PostType( $entry );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string[] $keys
	 * @return array<string,\WP_Post|null>
	 */
	public function loadKeys( array $keys ) {
		$post_types = \WPGraphQL::get_allowed_post_types( 'objects' );

		$loaded = [];
		if ( ! empty( $post_types ) && is_array( $post_types ) ) {
			foreach ( $keys as $key ) {
				if ( isset( $post_types[ $key ] ) ) {
					$loaded[ $key ] = $post_types[ $key ];
				} else {
					$loaded[ $key ] = null;
				}
			}
		}

		return $loaded;
	}
}
