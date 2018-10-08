<?php

namespace WPGraphQL\Data;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;

/**
 * Class MenuConnectionResolver
 *
 * @package WPGraphQL\Type\Menu\Connection
 * @since   0.0.30
 */
class MenuConnectionResolver extends TermObjectConnectionResolver {

	/**
	 * Return the term args to be used when getting the term object.
	 *
	 * @param mixed       $source  The query source being passed down to the resolver
	 * @param array       $args    The arguments that were provided to the query
	 * @param AppContext  $context Object containing app context that gets passed down the resolve tree
	 * @param ResolveInfo $info    Info about fields passed down the resolve tree
	 *
	 * @return array
	 * @throws \Exception
	 * @since  0.0.30
	 */
	public static function get_query_args( $source, array $args, AppContext $context, ResolveInfo $info ) {
		$term_args = [
			'hide_empty' => false,
			'include'    => [],
			'taxonomy'   => 'nav_menu',
		];

		if ( ! empty( $args['where']['slug'] ) ) {
			$term_args['slug'] = $args['where']['slug'];
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
