<?php

namespace WPGraphQL\Type;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\Type;

/**
 * Class TypeRegistry
 *
 * @package WPGraphQL\Type
 */
class TypeRegistry {

	/**
	 * Stores all registered Types
	 *
	 * @var array
	 */
	protected static $types;

	/**
	 * Stores a list of allowed Kinds of Types
	 *
	 * @var array
	 */
	protected static $allowed_kinds = [];

	/**
	 * Stores fields prepared for the Types
	 *
	 * @var array
	 */
	protected static $prepared_fields = [];

	/**
	 * Returns all registered Types
	 *
	 * @return array
	 */
	public static function get_types() {
		return self::$types;
	}

	/**
	 * Given a Type name, formats it for use as the Key in the Type Registry
	 *
	 * @param string $type_name The name of the Type in the registry
	 *
	 * @return string The key of the Type in the registry
	 */
	protected static function format_type_key( $type_name ) {
		return strtolower( $type_name );
	}

	/**
	 * Given the name of a Type, returns the Type definition
	 *
	 * @param string $type_name The name of the registered Type
	 *
	 * @return mixed|null
	 */
	public static function get_type( $type_name ) {
		return isset( self::$types[ $type_name ] ) ? self::$types[ $type_name ] : null;
	}

	/**
	 * Initialize the Type Registry
	 *
	 * - This defines the allowed_kinds of Types that can be registered (Enum, Object, etc)
	 * - This registers initial built-in Types
	 */
	public static function init() {

		/**
		 * Determine the allowed_kinds
		 */
		$kinds = [
			'enum',
			'object',
			'union',
			'input_object',
			'list_of',
			'non_null'
		];

		self::$allowed_kinds = apply_filters( 'graphql_type_registry_allowed_kinds', $kinds );

		self::$types['string']  = Type::string();
		self::$types['int']     = Type::int();
		self::$types['integer'] = Type::int();
		self::$types['float']   = Type::float();
		self::$types['id']      = Type::id();
		self::$types['boolean'] = Type::boolean();
		self::$types['bool']    = Type::boolean();

		/**
		 * Fire an action to register Types for the Schema
		 */
		do_action( 'graphql_register_types' );

	}

	/**
	 * Given an array of fields and the name of the Type they belong to, this prepares the fields
	 * for use in the Schema.
	 *
	 * This provides a filterable entry point for fields to be dynamically inserted onto a Type,
	 * escapes output of field descriptions,
	 *
	 * @param array  $fields    The array of fields
	 * @param string $type_name The name of the Type the fields belong to
	 *
	 * @return array
	 */
	public static function prepare_fields( array $fields = [], $type_name ) {

		if ( null === self::$prepared_fields ) {
			self::$prepared_fields = [];
		}

		$type_key = self::format_type_key( $type_name );

		if ( empty( self::$prepared_fields[ $type_key ] ) ) {

			/**
			 * Filter all object fields, passing the $typename as a param
			 *
			 * This is useful when several different types need to be easily filtered at once. . .for example,
			 * if ALL types with a field of a certain name needed to be adjusted, or something to that tune
			 *
			 * @param array  $fields    The array of fields for the object config
			 * @param string $type_name The name of the object type
			 */
			$fields = apply_filters( 'graphql_fields', $fields, $type_name );

			/**
			 * Filter the fields with the typename explicitly in the filter name
			 *
			 * This is useful for more targeted filtering, and is applied after the general filter, to allow for
			 * more specific overrides
			 *
			 * @param array $fields The array of fields for the object config
			 */
			$fields = apply_filters( "graphql_{$type_name}_fields", $fields, $type_name );

			/**
			 * This sorts the fields alphabetically by the key, which is super handy for making the schema readable,
			 * as it ensures it's not output in just random order
			 */
			ksort( $fields );

			/**
			 * Loop through the fields and
			 */
			foreach ( $fields as $key => $field_config ) {
				if ( isset( $field_config['type'] ) && is_string( $field_config['type'] ) ) {
					$type = TypeRegistry::get_type( $field_config['type'] );
					if ( isset( $type ) ) {
						$field_config['type'] = $type;
						if ( ! empty( $field_config['args'] ) && is_array( $field_config['args'] ) ) {
							foreach ( $field_config['args'] as $arg_key => $arg_config ) {
								$arg_type = TypeRegistry::get_type( $arg_config['type'] );
								if ( isset( $arg_type ) ) {
									$arg_config['type']               = $arg_type;
									$field_config['args'][ $arg_key ] = $arg_config;
								}
							}
						} else {
							unset( $field_config['args'] );
						}

						$fields[ $key ] = $field_config;
					} else {
						unset( $fields[ $key ] );
					}
				}
			}

			self::$prepared_fields[ $type_key ] = $fields;
		}

		return ! empty( self::$prepared_fields[ $type_key ] ) ? self::$prepared_fields[ $type_key ] : null;

	}

	/**
	 * Given a Type Name and Config options for the Type, this adds the Type to the TypeRegistry
	 *
	 * @param $type_name
	 * @param $config
	 *
	 * @throws \Exception
	 */
	public static function register_type( $type_name, $config ) {

		if ( ! isset( self::$types[ $type_name ] ) ) {
			if ( ! empty( $config ) && is_array( $config ) ) {

				$config['name']   = $type_name;
				$kind             = isset( $config['kind'] ) && in_array( $config['kind'], self::$allowed_kinds, true ) ? $config['kind'] : 'object';
				$config['fields'] = function () use ( $type_name, $config ) {
					$fields          = ! empty( $config['fields'] ) && is_array( $config['fields'] ) ? $config['fields'] : [];
					$prepared_fields = self::prepare_fields( $fields, $type_name );
					if ( empty( $prepared_fields ) ) {
						throw new UserError( sprintf( __( 'There are no fields registered for the %s Type' ), $type_name ) );
					}

					return $prepared_fields;
				};

				switch ( $kind ) {
					case 'enum':
						$type = new WPEnumType( $config );
						break;
					case 'union':
						$type = new WPUnionType( $config );
						break;
					case 'input_object':
					case 'input':
						$type = new WPInputObjectType( $config );
						break;
					case 'list_of':
					case 'listof':
					case 'list':
						// @todo: make this dynamic
						$type = Type::listOf( Type::string() );
						break;
					case 'non_null':
					case 'nonnull':
						// @todo: make this dynamic
						$type = Type::nonNull( Type::string() );
						break;
					case 'object':
					default:
						$type = new WPObjectType( $config );
						break;
				}

				self::$types[ $type_name ] = $type;

			} else {
				// Translators: The placeholder is the name of the Type being registered
				throw new \Exception( sprintf( __( 'The registered Type %s is missing the config', 'wp-graphql' ), $type_name ) );
			}
		} else {
			throw new \Exception( __( 'There is already a Type registered with this type_name', 'wp-graphql' ) );
		}

	}

	public static function register_field( $type_name, $field_name, $config ) {
		add_filter( 'graphql_' . $type_name . '_fields', function ( $fields ) use ( $type_name, $field_name, $config ) {

			if ( isset ( $fields[ $field_name ] ) ) {
				throw new \Exception( sprintf( __( 'The field %1$s already exists for the %2$s type', 'wp-graphql' ), $field_name, $type_name ) );
			}

			$type = isset( $config['type'] ) ? \WPGraphQL\Type\TypeRegistry::get_type( $config['type'] ) : null;

			if ( null === $type ) {
				throw new \Exception( sprintf( __( 'The field Type "%s" is not a registered type', 'wp-graphql' ), $config['type'] ) );
			}

			$config['name']        = $field_name;
			$config['type']        = $type;
			$fields[ $field_name ] = $config;

			return $fields;

		} );
	}

	public static function deregister_field( $type_name, $field_name ) {
		add_filter( 'graphql_' . $type_name . '_fields', function ( $fields ) use ( $type_name, $field_name ) {
			if ( ! isset( $fields[ $field_name ] ) ) {
				return $fields;
			}
			unset( $fields[ $field_name ] );

			return $fields;
		}, 100 );
	}

}