<?php
namespace WPGraphQL\Data\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Model\Menu;
use WPGraphQL\Model\MenuItem;

/**
 * Class MenuItemConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 */
class MenuItemConnectionResolver extends PostObjectConnectionResolver {

	/**
	 * MenuItemConnectionResolver constructor.
	 *
	 * @param             $source
	 * @param array       $args
	 * @param AppContext  $context
	 * @param ResolveInfo $info
	 *
	 * @throws \Exception
	 */
	public function __construct( $source, array $args, AppContext $context, ResolveInfo $info ) {
		parent::__construct( $source, $args, $context, $info, 'nav_menu_item' );
	}

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
		if ( $source instanceof Menu || $source instanceof \WP_Term && ! empty( $source->slug ) ) {
			return wp_get_nav_menu_items( $source->slug );
		}

		// Source object is a nav menu item via childItems or found via where arg.
		if ( $source instanceof MenuItem ) {

			// Get the nav menu that this nav menu item belongs to.
			if ( isset( $source->menu ) ) {
				if ( $source->menu instanceof Menu && ! empty( $source->menu->slug ) ) {
					$items = wp_get_nav_menu_items( $source->menu->slug );
					return $items;
				} elseif ( $source->menu instanceof MenuItem ) {
					return self::get_menu_items( $source->menu, $args );
				}
			} else {
				$menu = get_the_terms( $source->menuItemId, 'nav_menu' );
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
	 * @return array
	 * @throws \Exception
	 * @since  0.0.30
	 */
	public function get_query_args() {

		/**
		 * Filter the $this->args to allow folks to customize query generation programmatically
		 *
		 * @param array       $args       The inputArgs on the field
		 * @param mixed       $source     The source that's passed down the GraphQL queries
		 * @param AppContext  $context    The AppContext passed down the GraphQL tree
		 * @param ResolveInfo $info       The ResolveInfo passed down the GraphQL tree
		 */
		$args = apply_filters( 'graphql_menu_item_connection_args', $this->args, $this->source, $this->context, $this->info );

		// Prevent the query from matching anything by default.
		$query_args = [
			'post_type' => 'nav_menu_item',
			'post__in'  => [ 0 ],
		];

		$source = $this->source;

		// If the user requested a specific ID, set the source object accordingly.
		if ( ! empty( $args['where']['id'] ) ) {
			$source = get_post( (int) $args['where']['id'] );
			$source = new MenuItem( $source );
		}

		$menu_items = $this->get_menu_items( $source, $args );

		/**
		 * We need to query just the IDs so that the deferred resolution can handle fulfilling the
		 * objects either from the cache or via a follow-up query.
		 */
		$query_args['fields'] = 'ids';

		// No menu items? Nothing to do.
		if ( empty( $menu_items ) ) {
			return $query_args;
		}

		// Filter the menu items on whether they match a parent ID, if we are
		// inside a request for child items. If parent ID is 0, that corresponds to
		// a top-level menu item.
		$parent_id     = ( $source instanceof MenuItem && 'childItems' === $this->info->fieldName ) ? $source->menuItemId : 0;
		$matched_items = array_filter(
			$menu_items,
			function ( $item ) use ( $parent_id ) {
				return $parent_id === intval( $item->menu_item_parent );
			}
		);

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
		$query_args['posts_per_page'] = $this->get_query_amount() + absint( $pagination_increase );

		return $query_args;
	}

}
