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
	 * @param array $keys
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function loadKeys( array $keys ) {
		$post_types = get_post_types( [ 'show_in_graphql' => true ], 'objects' );

		$loaded = [];
		if ( ! empty( $post_types ) && is_array( $post_types ) ) {
			foreach ( $keys as $key ) {
				if ( isset( $post_types[ $key ] ) ) {
					$loaded[ $key ] = new PostType( $post_types[ $key ] );
				} else {
					$loaded[ $key ] = null;
				}
			}
		}

		return $loaded;

	}
}
