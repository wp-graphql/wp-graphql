<?php

namespace WPGraphQL\Data\Connection;

/**
 * Class MenuConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 */
class MenuConnectionResolver extends TermObjectConnectionResolver {

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

		if ( ! empty( $args['where']['slug'] ) ) {
			$term_args['slug']    = $args['where']['slug'];
			$term_args['include'] = null;
		}

		if ( ! empty( $args['where']['location'] ) ) {
			$theme_locations = get_nav_menu_locations();

			if ( isset( $theme_locations[ $args['where']['location'] ] ) ) {
				$term_args['include'] = $theme_locations[ $args['where']['location'] ];
			}
		}

		if ( ! empty( $args['where']['id'] ) ) {
			$term_args['include'] = $args['where']['id'];
		}

		return $term_args;
	}

}
