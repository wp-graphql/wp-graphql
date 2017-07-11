<?php
namespace WPGraphQL\Type\MenuItem\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Connection\ArrayConnection;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Type\PostObject\Connection\PostObjectConnectionResolver;

/**
 * Class ThemeConnectionResolver
 *
 * @package WPGraphQL\Data\Resolvers
 * @since 0.5.0
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

		/**
		 * Determine the menu_slug based on the $source of the query
		 */
		if ( $source instanceof \WP_Term ) {
			$menu_slug = ! empty( $source->slug ) ? $source->slug : null;
		} elseif ( $source instanceof  \WP_Post ) {
			$menu_slug = ! empty( $source->menu->slug ) ? $source->menu->slug : null;
		}

		/**
		 * If menuSlug was entered as an argument, use it
		 */
		if ( ! empty( $args['where']['menuSlug'] ) ) {
			$menu_slug = sanitize_text_field( $args['where']['menuSlug'] );
		}

		/**
		 * If the menuLocation is set in the $args, use it to set the menu slug
		 */
		if ( ! empty( $args['where']['menuLocation'] ) ) {
			$theme_locations = get_nav_menu_locations();
			$menu = wp_get_nav_menu_object( $theme_locations[ $args['where']['menuLocation'] ] );
			$menu_slug = $menu->slug;
		}

		/**
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


		/**
		 * If an argument was passed for a specific parentId, use it
		 */
		if ( ! empty( $args['where']['parentMenuItemId'] ) ) {
			$id_parts = Relay::fromGlobalId( $args['where']['parentMenuItemId'] );
			if ( is_array( $id_parts ) && ! empty( $id_parts['id'] ) ) {
				$parent_id = $id_parts['id'];
			}
		}

		$query_args['post_type'] = 'nav_menu_item';
		$query_args['posts_per_page'] = 100;

		/**
		 * Limit the query to items of a specific parent
		 */
		$query_args['meta_key'] = '_menu_item_menu_item_parent';
		$query_args['meta_value'] = $parent_id;

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

		$query_args['order'] = 'ASC';
		$query_args['orderby'] = 'menu_order';

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
