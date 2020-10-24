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
 * Formats the name of a Type so that it plays nice with GraphiQL
 *
 * @param string $type_name Name of the field
 *
 * @return string Name of the field
 * @since  0.0.2
 */
function graphql_format_type_name( $type_name ) {
	$type_name = preg_replace( '/[^A-Za-z0-9]/i', ' ', $type_name );
	$type_name = preg_replace( '/[^A-Za-z0-9]/i', '', ucwords( $type_name ) );
	$type_name = ucfirst( $type_name );

	return $type_name;
}


/**
 * Provides a simple way to run a GraphQL query with out posting a request to the endpoint.
 *
 * @param array $request_data The GraphQL request data (query, variables, operation_name).
 *
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
 *
 * @return string
 */
function get_graphql_register_action() {
	$action = 'graphql_register_types_late';
	if ( ! did_action( 'graphql_register_initial_types' ) ) {
		$action = 'graphql_register_initial_types';
	} elseif ( ! did_action( 'graphql_register_types' ) ) {
		$action = 'graphql_register_types';
	}

	return $action;
}

/**
 * Given a type name and interface name, this applies the interface to the Type.
 *
 * Should be used at the `graphql_register_types` hook.
 *
 * @param array $interface_names Array of one or more names of the GraphQL Interfaces to apply to
 *                               the GraphQL Types
 * @param array $type_names      Array of one or more names of the GraphQL Types to apply the
 *                               interfaces to
 *
 * example:
 * The following would register the "MyNewInterface" interface to the Post and Page type in the
 * Schema.
 *
 * register_graphql_interfaces_to_types( [ 'MyNewInterface' ], [ 'Post', 'Page' ] );
 */
function register_graphql_interfaces_to_types( $interface_names, $type_names ) {

	if ( is_string( $type_names ) ) {
		$type_names = [ $type_names ];
	}

	if ( is_string( $interface_names ) ) {
		$interface_names[] = $interface_names;
	}

	if ( ! empty( $type_names ) && is_array( $type_names ) && ! empty( $interface_names ) && is_array( $interface_names ) ) {
		foreach ( $type_names as $type_name ) {

			// Filter the GraphQL Object Type Interface to apply the interface
			add_filter( 'graphql_object_type_interfaces', function( $interfaces, $config ) use ( $type_name, $interface_names ) {

				$interfaces = is_array( $interfaces ) ? $interfaces : [];

				if ( strtolower( $type_name ) === strtolower( $config['name'] ) ) {
					$interfaces = array_unique( array_merge( $interfaces, $interface_names ) );
				}

				return $interfaces;
			}, 10, 2 );

		}
	}
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
		$type_registry->register_type( $type_name, $config );
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
		$type_registry->register_field( $type_name, $field_name, $config );
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
		$type_registry->register_fields( $type_name, $fields );
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
 * Given a config array for a custom Scalar, this registers a Scalar for use in the Schema
 *
 * @param string $type_name The name of the Type to register
 * @param array  $config    The config for the scalar type to register
 */
function register_graphql_scalar( $type_name, array $config ) {
	add_action( get_graphql_register_action(), function( \WPGraphQL\Registry\TypeRegistry $type_registry ) use ( $type_name, $config ) {
		$type_registry->register_scalar( $type_name, $config );
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

/**
 * Registers a GraphQL Settings Section
 *
 * @param string $slug   The slug of the group being registered
 * @param array  $config Array configuring the section. Should include: title
 */
function register_graphql_settings_section( $slug, $config ) {
	add_action( 'graphql_init_settings', function( \WPGraphQL\Admin\Settings\SettingsRegistry $registry ) use ( $slug, $config ) {
		$registry->register_section( $slug, $config );
	} );
}

/**
 * Registers a GraphQL Settings Field
 *
 * @param string $group  The name of the group to register a setting field to
 * @param array  $config The config for the settings field being registered
 */
function register_graphql_settings_field( $group, $config ) {
	add_action( 'graphql_init_settings', function( \WPGraphQL\Admin\Settings\SettingsRegistry $registry ) use ( $group, $config ) {
		$registry->register_field( $group, $config );
	} );
}

/**
 * Given a message and an optional config array
 *
 * @param mixed|string|array $message The debug message
 * @param array              $config  The debug config. Should be an associative array of keys and
 *                                    values.
 *                                    $config['type'] will set the "type" of the log, default type
 *                                    is GRAPHQL_DEBUG. Other fields added to $config will be
 *                                    merged into the debug entry.
 */
function graphql_debug( $message, $config = [] ) {
	$config['backtrace'] = wp_list_pluck( debug_backtrace(), 'file' );
	add_action( 'graphql_get_debug_log', function( \WPGraphQL\Utils\DebugLog $debug_log ) use ( $message, $config ) {
		return $debug_log->add_log_entry( $message, $config );
	} );
}

/**
 * Check if the name is valid for use in GraphQL
 *
 * @param $type_name
 *
 * @return bool
 */
function is_valid_graphql_name( $type_name ) {
	if ( preg_match( '/^\d/', $type_name ) ) {
		return false;
	}

	return true;
}

/**
 * Registers a series of GraphQL Settings Fields
 *
 * @param string $group  The name of the settings group to register fields to
 * @param array  $fields Array of field configs to register to the group
 */
function register_graphql_settings_fields( $group, $fields ) {
	add_action( 'graphql_init_settings', function( \WPGraphQL\Admin\Settings\SettingsRegistry $registry ) use ( $group, $fields ) {
		$registry->register_fields( $group, $fields );
	} );
}

/**
 * Get an option value from GraphQL settings
 *
 * @param string $option_name  The key of the option to return
 * @param mixed  $default      The default value the setting should return if no value is set
 * @param string $section_name The settings group section that the option belongs to
 *
 * @return mixed|string|int|boolean
 */
function get_graphql_setting( $option_name, $default = '', $section_name = 'graphql_general_settings' ) {

	$section_fields = get_option( $section_name );

	/**
	 * Filter the section fields
	 *
	 * @param array  $section_fields The values of the fields stored for the section
	 * @param string $section_name   The name of the section
	 * @param mixed  $default        The default value for the option being retrieved
	 */
	$section_fields = apply_filters( 'graphql_get_setting_section_fields', $section_fields, $section_name, $default );

	/**
	 * Get the value from the stored data, or return the default
	 */
	$value = isset( $section_fields[ $option_name ] ) ? $section_fields[ $option_name ] : $default;

	/**
	 * Filter the value before returning it
	 *
	 * @param mixed  $value          The value of the field
	 * @param mixed  $default        The default value if there is no value set
	 * @param string $option_name    The name of the option
	 * @param array  $section_fields The setting values within the section
	 * @param string $section_name   The name of the section the setting belongs to
	 */
	return apply_filters( 'graphql_get_setting_section_field_value', $value, $default, $option_name, $section_fields, $section_name );
}

/**
 * Polyfill for PHP versions below 7.3
 *
 * @return mixed|string|int
 */
if ( ! function_exists( 'array_key_first' ) ) {
	function array_key_first( array $array ) {
		foreach ( $array as $key => $value ) {
			return $key;
		}
	}
}

/**
 * Polyfill for PHP versions below 7.3
 *
 * @return mixed|string|int
 */
if ( ! function_exists( 'array_key_last' ) ) {
	function array_key_last( array $array ) {
		end( $array );

		return key( $array );
	}
}
