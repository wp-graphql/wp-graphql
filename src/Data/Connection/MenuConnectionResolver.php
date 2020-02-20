<?php

namespace WPGraphQL\Data\Connection;

use WPGraphQL\Model\Menu;

/**
 * Class MenuConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 */
class MenuConnectionResolver extends TermObjectConnectionResolver {

	/**
	 * @param $id
	 *
	 * @return mixed|null|\WPGraphQL\Model\Model|Menu
	 * @throws \Exception
	 */
	public function get_node_by_id( $id ) {
		$term = get_term( $id );
		return ! empty( $term ) && ! is_wp_error( $term ) ? new Menu( $term ) : null;
	}

	/**
	 * Get the connection args for use in WP_Term_Query to query the menus
	 *
	 * @return array
	 */
	public function get_query_args() {
		$term_args = [
			'hide_empty' => false,
			'include'    => [],
			'taxonomy'   => 'nav_menu',
			'fields'     => 'ids',
		];

		if ( ! empty( $this->args['where']['slug'] ) ) {
			$term_args['slug']    = $this->args['where']['slug'];
			$term_args['include'] = null;
		}

		if ( ! empty( $this->args['where']['location'] ) ) {
			$theme_locations = get_nav_menu_locations();

			if ( isset( $theme_locations[ $this->args['where']['location'] ] ) ) {
				$term_args['include'] = $theme_locations[ $this->args['where']['location'] ];
			}
		}

		if ( ! empty( $this->args['where']['id'] ) ) {
			$term_args['include'] = $this->args['where']['id'];
		}

		return $term_args;
	}

}
