<?php
namespace WPGraphQL\Data;

/**
 * Class Config
 *
 * This class contains configurations for various data-related things, such as query filters for cursor pagination.
 *
 * @package WPGraphQL\Data
 */
class Config {

	/**
	 * Config constructor.
	 */
	public function __construct() {

		/**
		 * Filter the WP_Query to support cursor based pagination where a post ID can be used
		 * as a point of comparison when slicing the results to return.
		 */
		add_filter( 'posts_where', [ $this, 'graphql_cursor_pagination_support' ], 10, 2 );

	}

	/**
	 * This filters the WPQuery 'where' $args, enforcing the query to return results before or after the
	 * referenced cursor
	 *
	 * @param string    $where The WHERE clause of the query.
	 * @param \WP_Query $query The WP_Query instance (passed by reference).
	 *
	 * @return string
	 */
	public function graphql_cursor_pagination_support( $where, \WP_Query $query ) {

		/**
		 * Access the global $wpdb object
		 */
		global $wpdb;

		/**
		 * If there's a graphql_cursor_offset in the query, we should check to see if
		 * it should be applied to the query
		 */
		if ( $cursor_offset = $query->get( 'graphql_cursor_offset' ) ) {

			/**
			 * Ensure the cursor_offset is a positive integer
			 */
			if ( is_integer( $cursor_offset ) && 0 < $cursor_offset ) {

				$compare = $query->get( 'graphql_cursor_compare' );
				$compare = in_array( $compare, [ '>', '<' ], true ) ? $compare : '<';

				/**
				 * Append the ID comparison to the WP_Query where clause
				 */
				$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID {$compare} %d ", absint( $cursor_offset ) );

			}

		}

		return $where;

	}

}
