<?php
/**
 * This file contains access functions for various class methods
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
 */
function graphql_query( $query, $variables ) {
	return \DFM\WPGraphQL::instance()->query( $query, $variables );
}