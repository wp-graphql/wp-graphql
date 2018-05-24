<?php

namespace WPGraphQL\Type\MenuItem\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Connection\ArrayConnection;
use WPGraphQL\AppContext;
use WPGraphQL\Type\PostObject\Connection\PostObjectConnectionResolver;

/**
 * Class MenuItemConnectionResolver
 *
 * @package WPGraphQL\Type\MenuItem\Connection
 * @since 0.0.29
 */
class MenuItemConnectionResolver extends PostObjectConnectionResolver {

	/**
	 * This returns the $query_args that should be used when querying for posts in the postObjectConnectionResolver.
	 * This checks what input $args are part of the query, combines them with various filters, etc and returns an
	 * array of $query_args to be used in the \WP_Query call
	 *
	 * @param mixed       $source  The query source being passed down to the resolver
	 * @param array       $args    The arguments that were provided to the query
	 * @param AppContext  $context Object containing app context that gets passed down the resolve tree
	 * @param ResolveInfo $info    Info about fields passed down the resolve tree
	 *
	 * @return array
	 * @throws \Exception
	 */
	public static function get_query_args( $source, array $args, AppContext $context, ResolveInfo $info ) {

		// Prevent the query from matching anything by default.
		$query_args = [
			'post_type' => 'nav_menu_item',
			'post__in'  => array( 0 ),
		];

		$menu_slug = null;
		$parent_id = 0;

		// Source object is a nav menu.
		if ( $source instanceof \WP_Term && ! empty( $source->slug ) ) {
			$menu_slug = $source->slug;
		}

		// Source object is a nav menu item, via childItems.
		if (
			$source instanceof \WP_Post &&
			'nav_menu_item' === get_post_type( $source ) &&
			'childItems' === $info->fieldName
		) {
			// Set the parent ID.
			$parent_id = $source->ID;

			// Get the nav menu that this nav menu item belongs to.
			$menus = get_terms( 'nav_menu', $source );
			if ( ! is_wp_error( $menus ) && ! empty( $menus ) ) {
				$menu_slug = $menus[0]->slug;
			}
		}

		// Menu slug can also available from user arg, but don't let the user
		// override the connection context.
		if ( empty( $menu_slug ) && ! empty( $args['where']['location'] ) ) {
			$theme_locations = get_nav_menu_locations();

			if ( isset( $theme_locations[ $args['where']['location'] ] ) ) {
				// This is a menu ID, not a slug, but we are just passing it to
				// wp_get_nav_menu_items so it's fine.
				$menu_slug = $theme_locations[ $args['where']['location'] ];
			}
		}

		// Instead of querying posts by the taxonomy, use wp_get_nav_menu_items so
		// that we are able to differentiate between parent and child nav items.
		// Otherwise we would need to use (slow) meta queries.
		$menu_items = wp_get_nav_menu_items( $menu_slug );

		// No menu items? Nothing to do.
		if ( empty( $menu_items ) ) {
			return $query_args;
		}

		// Filter the menu items on whether they match a parent ID. If parent ID
		// is 0, that corresponds to a top-level menu item.
		$matched_items = array_filter( $menu_items, function( $item ) use ( $parent_id ) {
			return $parent_id === intval( $item->menu_item_parent );
		} );

		// Get post IDs.
		$matched_ids = wp_list_pluck( $matched_items, 'ID' );

		// If the user requested a specific ID, check for it.
		if ( ! empty( $args['where']['id'] ) ) {
			$requested_ids = [ intval( $args['where']['id'] ) ];
			$matched_ids = array_intersect( $matched_ids, $requested_ids );
		}

		// Only update post__in if there are matches.
		if ( count( $matched_ids ) ) {
			$query_args['post__in'] = $matched_ids;
		}

		/**
		 * Set the order to match the menu order
		 */
		$query_args['order']   = 'ASC';
		$query_args['orderby'] = 'menu_order';

		/**
		 * Set the posts_per_page, ensuring it doesn't exceed the amount set as the $max_query_amount
		 */
		$pagination_increase = ! empty( $args['first'] ) && ( empty( $args['after'] ) && empty( $args['before'] ) ) ? 0 : 1;
		$query_args['posts_per_page'] = self::get_query_amount( $source, $args, $context, $info ) + absint( $pagination_increase );

		return $query_args;
	}

	/**
	 * Takes an array of items and returns the edges
	 *
	 * @param $items
	 *
	 * @return array
	 */
	public static function get_edges( $items, $source, $args, $context, $info ) {
		$edges = [];

		if ( ! empty( $items ) && is_array( $items ) ) {
			$items = array_reverse( $items );
			/**
			 * If the $items returned is more than the amount that was asked for, slice the array to match
			 */
			$query_amount = self::get_query_amount( $source, $args, $context, $info );
			if ( count( $items ) > $query_amount ) {
				$items = array_slice( $items, absint( $query_amount ) );
			}
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
