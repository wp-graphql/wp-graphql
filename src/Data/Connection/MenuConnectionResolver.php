<?php

namespace WPGraphQL\Data\Connection;

/**
 * Class MenuConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 */
class MenuConnectionResolver extends TermObjectConnectionResolver {

	/**
	 * {@inheritDoc}
	 *
	 * @throws \Exception
	 */
	public function get_query_args() {
		$term_args = [
			'hide_empty' => false,
			'include'    => [],
			'taxonomy'   => 'nav_menu',
			'fields'     => 'ids',
		];

		$args = $this->get_args();

		if ( ! empty( $args['where']['slug'] ) ) {
			$term_args['slug']    = $args['where']['slug'];
			$term_args['include'] = null;
		}

		$theme_locations = get_nav_menu_locations();

		// If a location is specified in the args, use it
		if ( ! empty( $args['where']['location'] ) ) {
			// Exclude unset and non-existent locations
			$term_args['include'] = ! empty( $theme_locations[ $args['where']['location'] ] ) ? $theme_locations[ $args['where']['location'] ] : -1;
			// If the current user cannot edit theme options
		} elseif ( ! current_user_can( 'edit_theme_options' ) ) {
			$term_args['include'] = array_values( $theme_locations );
		}

		if ( ! empty( $args['where']['id'] ) ) {
			$term_args['include'] = $args['where']['id'];
		}

		$query_args = parent::get_query_args();

		return array_merge( $query_args, $term_args );
	}
}
