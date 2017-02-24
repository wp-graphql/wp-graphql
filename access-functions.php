<?php
/**
 * This file contains access functions for various class methods
 * @since 0.0.2
 */

/**
 * Formats the name of a field so that it plays nice with GraphiQL
 *
 * @param string $field_name Name of the field
 * @access public
 * @return string Name of the field
 * @since  0.0.2
 */
function graphql_format_field_name( $field_name ) {
	$field_name = preg_replace( '/[^A-Za-z0-9]/i', ' ', $field_name );
	$field_name = preg_replace( '/[^A-Za-z0-9]/i', '', ucwords( $field_name ) );
	$field_name = lcfirst( $field_name );

	return $field_name;
}

/**
 * Provides a simple way to run a GraphQL query with out posting a request to the endpoint.
 *
 * @param string     $query     The GraphQL query to run
 * @param array|null $variables Variables to be passed to your GraphQL query
 * @access public
 * @return array
 * @since  0.0.2
 */
function do_graphql_request( $query, $variables = null ) {
	return \WPGraphQL::do_graphql_request( $query, $variables );
}
