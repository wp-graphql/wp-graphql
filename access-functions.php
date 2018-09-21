<?php
/**
 * This file contains access functions for various class methods
 *
 * @since 0.0.2
 */

/**
 * Formats the name of a field so that it plays nice with GraphiQL
 *
 * @param string $field_name Name of the field
 *
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
 * @param string $request          The GraphQL query to run
 * @param string $operation_name The name of the operation
 * @param string $variables      Variables to be passed to your GraphQL request
 *
 * @access public
 * @return array
 * @since  0.0.2
 */
function do_graphql_request( $request, $operation_name = '', $variables = '' ) {
	return \WPGraphQL::do_graphql_request( $request, $operation_name, $variables );
}

function register_graphql_type( $type_name, $config ) {
	\WPGraphQL\TypeRegistry::register_type( $type_name, $config );
}

function register_graphql_object_type( $type_name, $config ) {
	$config['kind'] = 'object';
	register_graphql_type( $type_name, $config );
}

function register_graphql_input_type( $type_name, $config ) {
	$config['kind'] = 'input';
	register_graphql_type( $type_name, $config );
}

function register_graphql_union_type( $type_name, $config ) {
	$config['kind'] = 'union';
	register_graphql_type( $type_name, $config );
}

function register_graphql_enum_type( $type_name, $config ) {
	$config['kind'] = 'enum';
	register_graphql_type( $type_name, $config );
}

function register_graphql_field( $type_name, $field_name, $config ) {
	\WPGraphQL\TypeRegistry::register_field( $type_name, $field_name, $config );
}

function register_graphql_fields( $type_name, array $fields ) {
	\WPGraphQL\TypeRegistry::register_fields( $type_name, $fields );
}

function register_graphql_schema( $schema_name, array $config ) {
	\WPGraphQL\SchemaRegistry::register_schema( $schema_name, $config );
}

function register_graphql_connection( $config ) {
	\WPGraphQL\TypeRegistry::register_connection( $config );
}

function deregister_graphql_field( $type_name, $field_name ) {
	\WPGraphQL\TypeRegistry::deregister_field( $type_name, $field_name );
}