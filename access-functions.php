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
 */
function do_graphql_request( $query, $operation_name = '', $variables = [] ) {
	return graphql( [
		'query'          => $query,
		'variables'      => $variables,
		'operation_name' => $operation_name,
	] );
}

/**
 * Given a Type Name and a $config array, this adds a Type to the TypeRegistry
 *
 * @param string $type_name The name of the Type to register
 * @param array  $config    The Type config
 */
function register_graphql_type( $type_name, $config ) {
	\WPGraphQL\TypeRegistry::register_type( $type_name, $config );
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
	$config['kind'] = 'union';
	register_graphql_type( $type_name, $config );
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
	\WPGraphQL\TypeRegistry::register_field( $type_name, $field_name, $config );
}

/**
 * Given a Type Name and an array of field configs, this adds the fields to the registered type in
 * the TypeRegistry
 *
 * @param string $type_name The name of the Type to add the fields to
 * @param array  $fields    An array of field configs
 */
function register_graphql_fields( $type_name, array $fields ) {
	\WPGraphQL\TypeRegistry::register_fields( $type_name, $fields );
}

/**
 * Given a Schema Name and a Schema Config, this adds the Schema to the SchemaRegistry
 *
 * @param string $schema_name The name of the Schema to register
 * @param array  $config      The config for the Schema
 */
function register_graphql_schema( $schema_name, array $config ) {
	\WPGraphQL\SchemaRegistry::register_schema( $schema_name, $config );
}

/**
 * Given a config array for a connection, this registers a connection by creating all appropriate
 * fields and types for the connection
 *
 * @param array $config Array to configure the connection
 */
function register_graphql_connection( array $config ) {
	\WPGraphQL\TypeRegistry::register_connection( $config );
}

/**
 * Given a Type Name and Field Name, this removes the field from the TypeRegistry
 *
 * @param string $type_name  The name of the Type to remove the field from
 * @param string $field_name The name of the field to remove
 */
function deregister_graphql_field( $type_name, $field_name ) {
	\WPGraphQL\TypeRegistry::deregister_field( $type_name, $field_name );
}

/**
 * Given a Mutation Name and Config array, this adds a Mutation to the Schema
 *
 * @param string $mutation_name The name of the Mutation to register
 * @param array  $config        The config for the mutation
 */
function register_graphql_mutation( $mutation_name, $config ) {
	\WPGraphQL\TypeRegistry::register_mutation( $mutation_name, $config );
}