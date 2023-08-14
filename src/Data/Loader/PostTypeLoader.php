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
	 * @param mixed $entry The User Role object
	 * @param mixed $key The Key to identify the user role by
	 *
	 * @return mixed|\WPGraphQL\Model\PostType
	 * @throws \Exception
	 */
	protected function get_model( $entry, $key ) {
		return new PostType( $entry );
	}

	/**
	 * @param array $keys
	 *
	 * @return array
	 * @throws \Exception
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
