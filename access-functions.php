<?php
/**
 * This file contains access functions for various class methods
 * @since 0.0.2
 */

function graphql_format_field_name( $field_name ) {
	$field_name = preg_replace( '/[^A-Za-z0-9]/i', ' ', $field_name );
	$field_name = preg_replace( '/[^A-Za-z0-9]/i', '', ucwords( $field_name ) );
	$field_name = lcfirst( $field_name );

	return $field_name;
}

function do_graphql_request( $query, $variables = null ) {
	return \WPGraphQL::do_graphql_request( $query, $variables );
}