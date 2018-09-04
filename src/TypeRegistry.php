<?php

namespace WPGraphQL;

use WPGraphQL\Type\RootMutationType;
use WPGraphQL\Type\RootQueryType;
use WPGraphQL\Type\WPObjectType;

class TypeRegistry {

	protected static $types;

	public static function init() {

		register_graphql_type( 'Bool', Types::boolean() );
		register_graphql_type( 'Boolean', Types::boolean() );
		register_graphql_type( 'Id', Types::id() );
		register_graphql_type( 'Int', Types::int() );
		register_graphql_type( 'Integer', Types::int() );
		register_graphql_type( 'Float', Types::float() );
		register_graphql_type( 'String', Types::string() );
		register_graphql_type( 'RootQuery', new RootQueryType() );
		register_graphql_type( 'RootMutation', new RootMutationType() );

		if ( ! did_action( 'graphql_register_types' ) ) {
			do_action( 'graphql_register_types' );
		}

	}

	protected static function prepare_key( $key ) {
		return strtolower( $key );
	}

	public static function register_fields( $type_name, $fields ) {
		if ( isset( $type_name ) && is_string( $type_name ) && ! empty( $fields ) && is_array( $fields ) ) {
			foreach ( $fields as $field_name => $config ) {
				if ( isset( $field_name ) && is_string( $field_name ) && ! empty( $config ) && is_array( $config ) ) {
					self::register_field( $type_name, $field_name, $config );
				}
			}
		}
	}

	public static function register_field( $type_name, $field_name, $config ) {

		add_filter( 'graphql_' . $type_name . '_fields', function( $fields ) use ( $type_name, $field_name, $config ) {

			if ( isset ( $fields[ $field_name ] ) ) {
				return $fields;
			}

			/**
			 * If the field returns a properly prepared field, add it the the field registry
			 */
			$field = self::prepare_field( $field_name, $config, $type_name );

			if ( ! empty( $field ) ) {
				$fields[ $field_name ] = self::prepare_field( $field_name, $config, $type_name );
			}

			return $fields;

		});

	}

	public static function register_type( $type_name, $config ) {
		if ( ! isset( self::$types[ $type_name ] ) ) {
			$prepared_type = self::prepare_type( $type_name, $config );
			if ( ! empty( $prepared_type ) ) {
				self::$types[ self::prepare_key( $type_name ) ] = $prepared_type;
			}
		}
	}

	protected static function prepare_type( $type_name, $config ) {

		if ( is_array( $config ) ) {
			$kind             = isset( $config['kind'] ) ? $config['kind'] : null;
			$config['name']   = $type_name;

			if ( ! empty( $config['fields'] ) && is_array( $config['fields'] ) ) {
				$config['fields'] = self::prepare_fields( $config['fields'], $type_name );
			}

			switch ( $kind ) {
				case 'object':
					$config['fields'] = function() use ( $config, $type_name ) {
						return WPObjectType::prepare_fields( $config['fields'], $type_name );
					};
					$prepared_type = new WPObjectType( $config );
			}
		} else {
			$prepared_type = $config;
		}

		return isset( $prepared_type ) ? $prepared_type : null;
	}

	protected static function prepare_fields( $fields, $type_name ) {
		$prepared_fields = [];
		if ( ! empty( $fields ) && is_array( $fields ) ) {
			foreach ( $fields as $field_name => $field_config ) {
				if ( isset( $field_config['type'] ) ) {
					$prepared_field = self::prepare_field( $field_name, $field_config, $type_name );
					if ( ! empty( $prepared_field ) ) {
						$prepared_fields[ self::prepare_key( $field_name ) ] = $prepared_field;
					} else {
						unset( $prepared_fields[ self::prepare_key( $field_name ) ] );
					}

				}
			}
		}

		return $prepared_fields;
	}

	protected static function prepare_field( $field_name, $field_config, $type_name ) {

		if ( ! isset( $field_config['name'] ) ) {
			$field_config['name'] = $field_name;
		}

		if ( is_string( $field_config['type'] ) ) {
			$type = TypeRegistry::get_type( $field_config['type'] );
			if ( ! empty( $type ) ) {
				$field_config['type'] = $type;
			} else {
				return null;
			}
		}

		if ( ! empty( $field_config['args'] ) && is_array( $field_config['args'] ) ) {
			foreach( $field_config['args'] as $arg => $arg_config ) {
				if ( isset( $arg_config['type'] ) ) {
					if ( is_string( $arg_config['type'] ) ) {
						$arg_config['type'] = TypeRegistry::get_type( $arg_config['type'] );
					}
				}
			}
		} else {
			unset( $field_config['args'] );
		}

		return $field_config;
	}

	public static function get_type( $type_name ) {
		return isset( self::$types[ self::prepare_key( $type_name ) ] ) ? self::$types[ self::prepare_key( $type_name ) ] : null;
	}
	public static function get_types() {
		return ! empty( self::$types ) ? self::$types : [];
	}

}