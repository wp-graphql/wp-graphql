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
	 * @param $entry
	 * @param $key
	 *
	 * @return mixed|PostType
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
		$post_types = get_post_types( [ 'show_in_graphql' => true ], 'objects' );

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
