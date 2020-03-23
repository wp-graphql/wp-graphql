<?php
/**
 * Resolves connections to menus
 *
 * @package WPGraphQL\Data\Connection
 */

namespace WPGraphQL\Data\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Model\Menu;

/**
 * Class Menu_Connection_Resolver
 */
class Menu_Connection_Resolver extends Term_Object_Connection_Resolver {

	/**
	 * Menu_Connection_Resolver constructor.
	 *
	 * @param mixed       $source  The query results.
	 * @param array       $args    The query arguments.
	 * @param AppContext  $context The AppContext object.
	 * @param ResolveInfo $info    The ResolveInfo object.
	 */
	public function __construct( $source, array $args, AppContext $context, ResolveInfo $info ) {
		parent::__construct( $source, $args, $context, $info, 'nav_menu' );
	}

	/**
	 * Given an ID, return the model for the entity or null
	 *
	 * @param int $id  Menu ID.
	 *
	 * @return Menu|null
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
