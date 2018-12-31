<?php

namespace WPGraphQL\Data;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Connection\ArrayConnection;
use WPGraphQL\AppContext;

/**
 * Class MenuItemConnectionResolver
 *
 * @package WPGraphQL\Type\MenuItem\Connection
 * @since   0.0.30
 */
class MenuItemConnectionResolver extends PostObjectConnectionResolver {

	/**
	 * Return an array of menu items associated with the menu connection, the
	 * requested menu location, or the specific menu item.
	 *
	 * Instead of querying posts by the taxonomy, use wp_get_nav_menu_items so
	 * that we are able to differentiate between parent and child nav items.
	 * Otherwise we would need to use (slow) meta queries.
	 *
	 * @param mixed $source The query source being passed down to the resolver
	 * @param array $args   The arguments that were provided to the query
	 *
	 * @return array
	 */
	private static function get_menu_items( $source, array $args ) {
		// Source object is a nav menu.
		if ( $source instanceof \WP_Term && ! empty( $source->slug ) ) {
			return wp_get_nav_menu_items( $source->slug );
		}

		// Source object is a nav menu item via childItems or found via where arg.
		if (
			$source instanceof \WP_Post &&
			'nav_menu_item' === get_post_type( $source )
		) {
			// Get the nav menu that this nav menu item belongs to.
			if ( isset( $source->menu ) ) {
				if ( $source->menu instanceof \WP_Term && ! empty( $source->menu->slug ) ) {
					return wp_get_nav_menu_items( $source->menu->slug );
				} else if ( $source->menu instanceof \WP_Post ) {
					return self::get_menu_items( $source->menu, $args );
				}
			} else {
				$menu = get_the_terms( $source, 'nav_menu' );
				if ( ! is_wp_error( $menu ) && ! empty( $menu ) && $menu[0] instanceof \WP_Term ) {
					return wp_get_nav_menu_items( $menu[0]->slug );
				}
			}
		}

		// Menu location can be available from user arg.
		if ( ! empty( $args['where']['location'] ) ) {
			$theme_locations = get_nav_menu_locations();

			if ( isset( $theme_locations[ $args['where']['location'] ] ) ) {
				return wp_get_nav_menu_items( $theme_locations[ $args['where']['location'] ] );
			}
		}

		return array();
	}

	/**
	 * This returns the $query_args that should be used when querying for posts in the
	 * menuItemConnectionResolver. This checks what input $args are part of the query, combines
	 * them with various filters, etc and returns an array of $query_args to be used in the
	 * \WP_Query call
	 *
	 * @param mixed       $source  The query source being passed down to the resolver
	 * @param array       $args    The arguments that were provided to the query
	 * @param AppContext  $context Object containing app context that gets passed down the resolve
	 *                             tree
	 * @param ResolveInfo $info    Info about fields passed down the resolve tree
	 *
	 * @return array
	 * @throws \Exception
	 * @since  0.0.30
	 */
	public static function get_query_args( $source, array $args, AppContext $context, ResolveInfo $info ) {

		// Prevent the query from matching anything by default.
		$query_args = [
			'post_type' => 'nav_menu_item',
			'post__in'  => array( 0 ),
		];

		// If the user requested a specific ID, set the source object accordingly.
		if ( ! empty( $args['where']['id'] ) ) {
			$source = DataSource::resolve_post_object( intval( $args['where']['id'] ), 'nav_menu_item' );
		}

		$menu_items = self::get_menu_items( $source, $args );

		// No menu items? Nothing to do.
		if ( empty( $menu_items ) ) {
			return $query_args;
		}

		// Filter the menu items on whether they match a parent ID, if we are
		// inside a request for child items. If parent ID is 0, that corresponds to
		// a top-level menu item.
		$parent_id     = ( $source instanceof \WP_Post && 'childItems' === $info->fieldName ) ? $source->ID : 0;
		$matched_items = array_filter( $menu_items, function ( $item ) use ( $parent_id ) {
			return $parent_id === intval( $item->menu_item_parent );
		} );

		// Get post IDs.
		$matched_ids = wp_list_pluck( $matched_items, 'ID' );

		// If the user requested a specific ID, check for it.
		if ( ! empty( $args['where']['id'] ) ) {
			$requested_ids = [ intval( $args['where']['id'] ) ];
			$matched_ids   = array_intersect( $matched_ids, $requested_ids );
		}

		// Only update post__in if there are matches.
		if ( count( $matched_ids ) ) {
			$query_args['post__in'] = $matched_ids;
		}

		/**
		 * Set the order to match the menu order
		 */
		$query_args['order']   = 'ASC';
		$query_args['orderby'] = 'post__in';

		/**
		 * Set the posts_per_page, ensuring it doesn't exceed the amount set as the $max_query_amount
		 */
		$pagination_increase          = ! empty( $args['first'] ) && ( empty( $args['after'] ) && empty( $args['before'] ) ) ? 0 : 1;
		$query_args['posts_per_page'] = self::get_query_amount( $source, $args, $context, $info ) + absint( $pagination_increase );

		return $query_args;
	}

	/**
	 * Takes an array of items and returns the edges
	 *
	 * @param $items
	 *
	 * @return array
	 * @since  0.0.30
	 */
	public static function get_edges( $items, $source, $args, $context, $info ) {
		$edges = [];

		if ( ! empty( $items ) && is_array( $items ) ) {
			foreach ( $items as $item ) {

				/**
				 * Add the menu as context to each item to pass down the graph
				 */
				$item->menu = $source;

				/**
				 * Create the edges to pass to the resolver
				 */
				$edges[] = [
					'cursor' => ArrayConnection::offsetToCursor( $item->ID ),
					'node'   => wp_setup_nav_menu_item( $item ),
				];
			}
		}

		return $edges;
	}

}
