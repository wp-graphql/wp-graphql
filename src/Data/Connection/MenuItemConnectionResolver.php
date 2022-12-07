<?php
namespace WPGraphQL\Data\Connection;

use Exception;
use GraphQLRelay\Relay;
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
	 * MenuItemConnectionResolver constructor.
	 *
	 * @param mixed       $source     source passed down from the resolve tree
	 * @param array       $args       array of arguments input in the field as part of the GraphQL query
	 * @param AppContext  $context    Object containing app context that gets passed down the resolve tree
	 * @param ResolveInfo $info       Info about fields passed down the resolve tree
	 *
	 * @throws Exception
	 */
	public function __construct( $source, array $args, AppContext $context, ResolveInfo $info ) {
		parent::__construct( $source, $args, $context, $info, 'nav_menu_item' );
	}

	/**
	 * Returns the query args for the connection to resolve with
	 *
	 * @return array
	 */
	public function get_query_args() {
		/**
		 * Prepare for later use
		 */
		$last = ! empty( $this->args['last'] ) ? $this->args['last'] : null;

		$menu_locations = get_theme_mod( 'nav_menu_locations' );

		$query_args            = parent::get_query_args();
		$query_args['orderby'] = 'menu_order';
		$query_args['order']   = isset( $last ) ? 'DESC' : 'ASC';

		if ( isset( $this->args['where']['parentDatabaseId'] ) ) {
			$query_args['meta_key']   = '_menu_item_menu_item_parent'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$query_args['meta_value'] = (int) $this->args['where']['parentDatabaseId']; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}

		if ( ! empty( $this->args['where']['parentId'] ) || ( isset( $this->args['where']['parentId'] ) && 0 === (int) $this->args['where']['parentId'] ) ) {
			$query_args['meta_key']   = '_menu_item_menu_item_parent'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$query_args['meta_value'] = $this->args['where']['parentId']; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}

		// Get unique list of locations as the default limitation of
		// locations to allow public queries for.
		// Public queries should only be allowed to query for
		// Menu Items assigned to a Menu Location
		$locations = is_array( $menu_locations ) && ! empty( $menu_locations ) ? array_unique( array_values( $menu_locations ) ) : [];

		// If the location argument is set, set the argument to the input argument
		if ( isset( $this->args['where']['location'], $menu_locations[ $this->args['where']['location'] ] ) ) {

			$locations = [ $menu_locations[ $this->args['where']['location'] ] ];

			// if the $locations are NOT set and the user has proper capabilities, let the user query
			// all menu items connected to any menu
		} elseif ( current_user_can( 'edit_theme_options' ) ) {
			$locations = null;
		}

		// Only query for menu items in assigned locations.
		if ( ! empty( $locations ) && is_array( $locations ) ) {

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
	 * Filters the GraphQL args before they are used in get_query_args().
	 *
	 * @return array
	 */
	public function get_args(): array {
		$args = $this->args;

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
		 * @param array $args            The GraphQL args passed to the resolver.
		 * @param array $unfiltered_args Array of arguments input in the field as part of the GraphQL query.
		 *
		 * @since 1.11.0
		 */
		return apply_filters( 'graphql_menu_item_connection_args', $args, $this->args );
	}

}
