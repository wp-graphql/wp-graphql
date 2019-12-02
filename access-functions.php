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
 * @param array $request_data The GraphQL request data (query, variables, operation_name).
 *
 * @access public
 * @return array
 * @since  0.2.0
 * @throws Exception
 */
function graphql( $request_data = [] ) {
	$request = new \WPGraphQL\Request( $request_data );
	return $request->execute();
}

/**
 * Previous access function for running GraphQL queries directly. This function will
 * eventually be deprecated in favor of `graphql`.
 *
 * @param string $query          The GraphQL query to run
 * @param string $operation_name The name of the operation
 * @param array  $variables      Variables to be passed to your GraphQL request
 *
 * @access public
 * @return array
 * @since  0.0.2
 * @throws \Exception
 */
function do_graphql_request( $query, $operation_name = '', $variables = [] ) {
	return graphql( [
		'query'          => $query,
		'variables'      => $variables,
		'operation_name' => $operation_name,
	] );
}

/**
 * Determine when to register types
 * @return string
 */
function get_graphql_register_action() {
	$action = 'graphql_register_types_late';
	if ( ! did_action( 'graphql_register_initial_types' ) ) {
		$action = 'graphql_register_initial_types';
	} else if ( ! did_action( 'graphql_register_types' ) ) {
		$action = 'graphql_register_types';
	}
	return $action;
}

/**
 * Given a Type Name and a $config array, this adds a Type to the TypeRegistry
 *
 * @param string $type_name The name of the Type to register
 * @param array  $config    The Type config
 */
function register_graphql_type( $type_name, $config ) {
	add_action( get_graphql_register_action(), function( \WPGraphQL\Registry\TypeRegistry $type_registry ) use ( $type_name, $config ) {
		$type_registry->register_type( $type_name, $config );
	}, 10 );

}

/**
 * Given a Type Name and a $config array, this adds an Interface Type to the TypeRegistry
 *
 * @param string $type_name The name of the Type to register
 * @param array  $config    The Type config
 */
function register_graphql_interface_type( $type_name, $config ) {
	add_action( get_graphql_register_action(), function( \WPGraphQL\Registry\TypeRegistry $type_registry ) use ( $type_name, $config ) {
		$type_registry->register_interface_type( $type_name, $config );
	}, 10 );
}

/**
 * Given a Type Name and a $config array, this adds an ObjectType to the TypeRegistry
 *
 * @param string $type_name The name of the Type to register
 * @param array  $config    The Type config
 */
function register_graphql_object_type( $type_name, $config ) {
	$config['kind'] = 'object';
	register_graphql_type( $type_name, $config );
}

/**
 * Given a Type Name and a $config array, this adds an InputType to the TypeRegistry
 *
 * @param string $type_name The name of the Type to register
 * @param array  $config    The Type config
 */
function register_graphql_input_type( $type_name, $config ) {
	$config['kind'] = 'input';
	register_graphql_type( $type_name, $config );
}

/**
 * Given a Type Name and a $config array, this adds an UnionType to the TypeRegistry
 *
 * @param string $type_name The name of the Type to register
 * @param array  $config    The Type config
 */
function register_graphql_union_type( $type_name, $config ) {

	add_action( get_graphql_register_action(), function( \WPGraphQL\Registry\TypeRegistry $type_registry ) use ( $type_name, $config ) {
		$config['kind'] = 'union';
		$type_registry->register_type( $type_name, $config  );
	}, 10 );
}

/**
 * Given a Type Name and a $config array, this adds an EnumType to the TypeRegistry
 *
 * @param string $type_name The name of the Type to register
 * @param array  $config    The Type config
 */
function register_graphql_enum_type( $type_name, $config ) {
	$config['kind'] = 'enum';
	register_graphql_type( $type_name, $config );
}

/**
 * Given a Type Name, Field Name, and a $config array, this adds a Field to a registered Type in
 * the TypeRegistry
 *
 * @param string $type_name  The name of the Type to add the field to
 * @param string $field_name The name of the Field to add to the Type
 * @param array  $config     The Type config
 */
function register_graphql_field( $type_name, $field_name, $config ) {
	add_action( get_graphql_register_action(), function( \WPGraphQL\Registry\TypeRegistry $type_registry ) use ( $type_name, $field_name, $config ) {
		$type_registry->register_field( $type_name, $field_name, $config  );
	}, 10 );
}

/**
 * Given a Type Name and an array of field configs, this adds the fields to the registered type in
 * the TypeRegistry
 *
 * @param string $type_name The name of the Type to add the fields to
 * @param array  $fields    An array of field configs
 */
function register_graphql_fields( $type_name, array $fields ) {
	add_action( get_graphql_register_action(), function( \WPGraphQL\Registry\TypeRegistry $type_registry ) use ( $type_name, $fields ) {
		$type_registry->register_fields( $type_name, $fields  );
	}, 10 );
}

/**
 * Given a config array for a connection, this registers a connection by creating all appropriate
 * fields and types for the connection
 *
 * @param array $config Array to configure the connection
 */
function register_graphql_connection( array $config ) {
	add_action( get_graphql_register_action(), function( \WPGraphQL\Registry\TypeRegistry $type_registry ) use ( $config ) {
		$type_registry->register_connection( $config );
	}, 10 );
}

/**
 * Given a Type Name and Field Name, this removes the field from the TypeRegistry
 *
 * @param string $type_name  The name of the Type to remove the field from
 * @param string $field_name The name of the field to remove
 */
function deregister_graphql_field( $type_name, $field_name ) {
	add_action( get_graphql_register_action(), function( \WPGraphQL\Registry\TypeRegistry $type_registry ) use ( $type_name, $field_name ) {
		$type_registry->deregister_field( $type_name, $field_name );
	}, 10 );
}

/**
 * Given a Mutation Name and Config array, this adds a Mutation to the Schema
 *
 * @param string $mutation_name The name of the Mutation to register
 * @param array  $config        The config for the mutation
 */
function register_graphql_mutation( $mutation_name, $config ) {
	add_action( get_graphql_register_action(), function( \WPGraphQL\Registry\TypeRegistry $type_registry ) use ( $mutation_name, $config ) {
		$type_registry->register_mutation( $mutation_name, $config );
	}, 10 );
}

/**
 * Whether a GraphQL request is in action or not. This is determined by the WPGraphQL Request
 * class being initiated. True while a request is in action, false after a request completes.
 *
 * This should be used when a condition needs to be checked for ALL GraphQL requests, such
 * as filtering WP_Query for GraphQL requests, for example.
 *
 * Default false.
 *
 * @since 0.4.1
 * @return bool
 */
function is_graphql_request() {
	return WPGraphQL::is_graphql_request();
}
/**
 * Whether a GraphQL HTTP request is in action or not. This is determined by
 * checking if the request is occurring on the route defined for the GraphQL endpoint.
 *
 * This conditional should only be used for features that apply to HTTP requests. If you are going
 * to apply filters to underlying WordPress core functionality that should affect _all_ GraphQL
 * requests, you should use "is_graphql_request" but if you need to apply filters only if the
 * GraphQL request is an HTTP request, use this conditional.
 *
 * Default false.
 *
 * @since 0.4.1
 * @return bool
 */
function is_graphql_http_request() {
	return \WPGraphQL\Router::is_graphql_http_request();
}
