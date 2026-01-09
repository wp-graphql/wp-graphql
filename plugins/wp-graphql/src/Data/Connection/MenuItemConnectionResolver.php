<?php
namespace WPGraphQL\Data\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Utils\Utils;

/**
 * Class MenuItemConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 */
class MenuItemConnectionResolver extends PostObjectConnectionResolver {

	/**
	 * {@inheritDoc}
	 */
	public function __construct( $source, array $args, AppContext $context, ResolveInfo $info ) {
		parent::__construct( $source, $args, $context, $info, 'nav_menu_item' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function prepare_query_args( array $args ): array {
		/**
		 * Prepare for later use
		 */
		$last = ! empty( $args['last'] ) ? $args['last'] : null;

		$menu_locations = get_theme_mod( 'nav_menu_locations' );

		$query_args            = parent::prepare_query_args( $args );
		$query_args['orderby'] = 'menu_order';
		$query_args['order']   = isset( $last ) ? 'DESC' : 'ASC';

		if ( isset( $args['where']['parentDatabaseId'] ) ) {
			$query_args['meta_key']   = '_menu_item_menu_item_parent';
			$query_args['meta_value'] = (int) $args['where']['parentDatabaseId']; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}

		if ( ! empty( $args['where']['parentId'] ) || ( isset( $args['where']['parentId'] ) && 0 === (int) $args['where']['parentId'] ) ) {
			$query_args['meta_key']   = '_menu_item_menu_item_parent';
			$query_args['meta_value'] = $args['where']['parentId']; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}

		// Get unique list of location term IDs as the default limitation of locations to allow public queries for.
		// Public queries should only be allowed to query for Menu Items assigned to a Menu Location.
		$locations = is_array( $menu_locations ) && ! empty( $menu_locations ) ? array_unique( array_values( $menu_locations ) ) : [];

		// If the location argument is set, set the argument to the input argument
		if ( ! empty( $args['where']['location'] ) ) {
			$locations = isset( $menu_locations[ $args['where']['location'] ] ) ? [ $menu_locations[ $args['where']['location'] ] ] : []; // We use an empty array to prevent fetching all media items if the location has no items assigned.

		} elseif ( current_user_can( 'edit_theme_options' ) ) {
			// If the $locations are NOT set, let a user with proper capability query all menu items.
			$locations = null;
		}

		// Only query for menu items in assigned locations.
		if ( isset( $locations ) ) {

			// unset the location arg
			// we don't need this passed as a taxonomy parameter to wp_query
			unset( $query_args['location'] );

			$query_args['tax_query'][] = [
				'taxonomy'         => 'nav_menu',
				'field'            => 'term_id',
				'terms'            => $locations,
				'include_children' => false,
				'operator'         => 'IN',
			];
		}

		return $query_args;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function prepare_args( array $args ): array {
		if ( ! empty( $args['where'] ) ) {
			// Ensure all IDs are converted to database IDs.
			foreach ( $args['where'] as $input_key => $input_value ) {
				if ( empty( $input_value ) ) {
					continue;
				}

				switch ( $input_key ) {
					case 'parentId':
						$args['where'][ $input_key ] = Utils::get_database_id_from_id( $input_value );
						break;
				}
			}
		}

		/**
		 *
		 * Filters the GraphQL args before they are used in get_query_args().
		 *
		 * @param array<string,mixed> $args            The GraphQL args passed to the resolver.
		 * @param array<string,mixed> $unfiltered_args Array of arguments input in the field as part of the GraphQL query.
		 *
		 * @since 1.11.0
		 */
		return apply_filters( 'graphql_menu_item_connection_args', $args, $this->get_unfiltered_args() );
	}
}
