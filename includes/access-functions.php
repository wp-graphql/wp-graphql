<?php
/**
 * This file contains access functions for various class methods
 * @since 0.0.2
*/

/**
 * graphql_query
 *
 * This accepts a query and variables and returns the results of the query.
 * This allows for graphql queries to be easily made inside of WordPress instead
 * of strictly via HTTP requests.
 *
 * @param $query
 * @param $variables
 * @return array
 * @since 0.0.2
 */
function graphql_query( $query, $variables ) {
	return \WPGraphQL::instance()->query( $query, $variables );
}
