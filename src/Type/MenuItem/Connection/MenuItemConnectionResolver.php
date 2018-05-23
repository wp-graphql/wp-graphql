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

		$query_args = [];

		/**
		 * Determine the menu_slug based on the $source of the query or from query
		 * args.
		 */
		if ( $source instanceof \WP_Term ) {
			$menu_slug = ! empty( $source->slug ) ? $source->slug : null;
		} elseif ( $source instanceof \WP_Post ) {
			$menu_slug = ! empty( $source->menu->slug ) ? $source->menu->slug : null;
		} elseif ( ! empty( $args['where']['location'] ) ) {
			$menu_slug = $args['where']['location'];
		}

		/**
		 * Allow menu items to be queried by ID.
		 */
		if ( ! empty( $args['where']['id'] ) ) {
			$query_args['post__in'] = [ intval( $args['where']['id'] ) ];
		}

		/**
		 * **NOT CURRENTLY IMPLEMENTED**
		 * If the source of the query is another nav_menu_item, and
		 * the field is "childItems" set the value of the $menu_item_parent to
		 */
		if (
			$source instanceof \WP_Post &&
			'nav_menu_item' === get_post_type( $source ) &&
			'childItems' === $info->fieldName
		) {
			$parent_id = $source->ID;
		} else {
			$parent_id = 0;
		}

		$query_args['post_type'] = 'nav_menu_item';

		/**
		 * Limit the query to items of a specific menu
		 */
		if ( ! empty( $menu_slug ) ) {
			$query_args['tax_query'] = [
				[
					'taxonomy' => 'nav_menu',
					'field'    => 'slug',
					'terms'    => [ $menu_slug ],
				],
			];
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
