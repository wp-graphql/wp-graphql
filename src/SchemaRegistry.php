<?php

namespace WPGraphQL;

/**
 * Class SchemaRegistry
 *
 * This allows for multiple Schemas to be registered and used for GraphQL validation.
 *
 * @package WPGraphQL
 */
class SchemaRegistry {

	/**
	 * Holds the registered Schemas
	 *
	 * @var array
	 */
	protected static $schemas;

	/**
	 * Initialize the Schema Registry
	 */
	public static function init() {

		/**
		 * Register the core GraphQL Schema
		 */
		register_graphql_schema(
			'core',
			[
				'query'    => 'RootQuery',
				'mutation' => 'RootMutation',
			]
		);

		if ( ! did_action( 'graphql_register_schemas' ) ) {
			do_action( 'graphql_register_schemas' );
		}
	}

	/**
	 * Returns all registered Schemas
	 *
	 * @return array
	 */
	public static function get_schemas() {
		return ! empty( self::$schemas ) && is_array( self::$schemas ) ? self::$schemas : [];
	}

	/**
	 * Given a Schema Name, returns the Schema associated with it
	 *
	 * @param string $schema_name The name of the Schema to return
	 *
	 * @return array|mixed
	 */
	public static function get_schema( $schema_name ) {
		return ! empty( self::$schemas[ $schema_name ] ) && is_array( self::$schemas[ $schema_name ] ) ? self::$schemas[ $schema_name ] : [];
	}

	/**
	 * Given a Schema Name and an array of Schema Config, this adds a Schema to the registry
	 *
	 * Schemas must be registered with a unique name. A Schema registered with an existing Schema
	 * name will not be registered.
	 *
	 * @param string $schema_name The name of the Schema to register
	 * @param array  $config      The config for the Schema to register
	 */
	public static function register_schema( $schema_name, $config ) {
		if ( isset( $schema_name ) && is_string( $schema_name ) && ! empty( $config ) && is_array( $config ) && ! isset( self::$schemas[ $schema_name ] ) ) {
			self::$schemas[ $schema_name ] = self::prepare_schema_config( $config );
		}
	}

	/**
	 * Given a Schema Name, this removes it from the registry
	 *
	 * @param string $schema_name The name of the Schema to remove from the Registry
	 */
	public static function deregister_schema( $schema_name ) {
		if ( isset( self::$schemas[ $schema_name ] ) ) {
			unset( self::$schemas[ $schema_name ] );
		}
	}

	/**
	 * Given the name of a Schema and Config, this prepares it for use in the Registry
	 *
	 * @param array $config The config for the Schema to register
	 *
	 * @return array
	 */
	protected static function prepare_schema_config( $config ) {

		$prepared_schema = [];

		if ( ! empty( $config ) && is_array( $config ) ) {
			foreach ( $config as $field => $type ) {
				if ( is_string( $type ) ) {
					$type = TypeRegistry::get_type( $type );
					if ( ! empty( $type ) ) {
						$prepared_schema[ $field ] = TypeRegistry::get_type( $type );
					}
				} else {
					$prepared_schema[ $field ] = TypeRegistry::get_type( $type );
				}
			}
		}

		return $prepared_schema;

	}

}
