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
 * @param string $request        The GraphQL query to run
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

/**
 * @param string $type_name The name of the Type being registered
 * @param array  $config    The config for the Type
 *
 * @throws Exception
 */
function register_graphql_type( $type_name, $config ) {
	\WPGraphQL\Type\TypeRegistry::register_type( $type_name, $config );
}

/**
 * Register a field to a Type in the Schema
 *
 * @param string $type_name  The name of the Type the field should be registered to
 * @param string $field_name The name of the field being registered
 * @param array  $config     The config for the field
 *
 * @throws \Exception
 */
function register_graphql_field( $type_name, $field_name, $config ) {
	\WPGraphQL\Type\TypeRegistry::register_field( $type_name, $field_name, $config );
}

/**
 * Register a field to a Type in the Schema
 *
 * @param string $type_name  The name of the Type the field should be registered to
 * @param string $field_name The name of the field being registered
 */
function deregister_graphql_field( $type_name, $field_name ) {
	\WPGraphQL\Type\TypeRegistry::deregister_field( $type_name, $field_name );
}

add_action( 'graphql_register_types', function () {

	$config = [
		'kind'        => 'object',
		'description' => __( 'The Root Query Type, which is the main entry point into the Graph', 'wp-graphql' ),
		'fields' => [
			'goo' => [
				'args' => [
					'input' => [
						'type' => 'GooInput',
					],
					'anotherInput' => [
						'type' => 'string',
					],
					'moreInput' => [
						'type' => 'boolean',
					],
				],
				'type' => 'string',
				'description' => __( 'The Goo Type' ),
				'resolve' => function() {
					return 'gooooooo!!!!';
				}
			],
		],
	];

	register_graphql_type( 'RootQuery', $config );
	register_graphql_type( 'GooInput', [
		'kind' => 'input_object',
		'description' => __( 'The Goo Input', 'wp-graphql' ),
		'fields' => [
			'hello' => [
				'type' => 'string',
			],
		],
	] );

	register_graphql_type( 'Post', [
		'kind' => 'object',
		'description' => __( 'Post objects', 'wp-graphql' ),
		'fields' => [
			'childPost' => [
				'args' => [
					'input' => [
						'type' => 'string',
					],
				],
				'type' => 'Post',
			],
			'id' => [
				'type' => 'ID',
			]
		]
	] );

	register_graphql_field( 'RootQuery', 'post', [
		'type'        => 'Post',
		'description' => __( 'Root Query Post Field', 'wp-graphql' ),
		'resolve'     => function () {
			return 'post...';
		}
	] );

	register_graphql_field( 'RootQuery', 'Gaa', [
		'type'        => 'string',
		'description' => __( 'Gaa Test Field' ),
		'resolve'     => function () {
			return 'GAAA';
		}
	] );

	// Uncomment me to de-register the `Gaa` field from the RootQuery
	// deregister_graphql_field( 'RootQuery', 'Gaa' );

} );