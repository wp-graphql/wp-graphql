<?php

namespace WPGraphQL\Type;

use Exception;
use GraphQL\Error\UserError;
use GraphQL\Type\Definition\InterfaceType;
use WPGraphQL\Registry\TypeRegistry;

class WPInterfaceType extends InterfaceType {

	use WPInterfaceTrait;

	/**
	 * Instance of the TypeRegistry as an Interface needs knowledge of available Types
	 *
	 * @var TypeRegistry
	 */
	public $type_registry;

	/**
	 * WPInterfaceType constructor.
	 *
	 * @param array        $config
	 * @param TypeRegistry $type_registry
	 *
	 * @throws Exception
	 */
	public function __construct( array $config, TypeRegistry $type_registry ) {

		$this->type_registry = $type_registry;

		$interfaces = isset( $config['interfaces'] ) ? $config['interfaces'] : [];

		/**
		 * Filters the interfaces applied to an object type
		 *
		 * @param array        $interfaces     List of interfaces applied to the Object Type
		 * @param array        $config         The config for the Object Type
		 * @param WPObjectType $wp_object_type The WPObjectType instance
		 */
		$interfaces               = apply_filters( 'graphql_interface_type_interfaces', $interfaces, $config, $this );
		$config['interfaceNames'] = $interfaces;

		/**
		 * Convert Interfaces from Strings to Types
		 */
		$config['interfaces'] = function() use ( $interfaces ) {

			if ( ! is_array( $interfaces ) || empty( $interfaces ) ) {
				return [];
			}

			return $this->get_implemented_interfaces( $interfaces );
		};

		$name             = ucfirst( $config['name'] );
		$config['name']   = apply_filters( 'graphql_type_name', $name, $config, $this );
		$config['fields'] = function() use ( $config ) {

			$fields = $config['fields'];

			/**
			 * Get the fields of interfaces and ensure they exist as fields of this type.
			 *
			 * Types are still responsible for ensuring the fields resolve properly.
			 */
			if ( ! empty( $config['interfaceNames'] ) ) {
				// Throw if "interfaceNames" invalid.
				if ( ! is_array( $config['interfaceNames'] ) ) {
					throw new UserError(
						sprintf(
						/* translators: %s: type name */
							__( 'Invalid value provided as "interfaceNames" on %s.', 'wp-graphql' ),
							$config['name']
						)
					);
				}

				$interface_fields = [];

				foreach ( $config['interfaceNames'] as $interface_name ) {
					$interface_type = null;
					if ( is_string( $interface_name ) ) {
						$interface_type = $this->type_registry->get_type( $interface_name );
					} elseif ( $interface_name instanceof WPInterfaceType ) {
						$interface_type = $interface_name;
					}

					if ( ! empty( $interface_type ) && $interface_type instanceof WPInterfaceType ) {

						$interface_config_fields = $interface_type->getFields();

						if ( ! empty( $interface_config_fields ) ) {
							foreach ( $interface_config_fields as $interface_field ) {
								$interface_fields[ $interface_field->name ] = $interface_field->config;
							}
						}
					}
				}

				$fields = array_replace_recursive( $interface_fields, $fields );
			}

			$fields = $this->prepare_fields( $fields, $config['name'] );
			$fields = $this->type_registry->prepare_fields( $fields, $config['name'] );

			return $fields;
		};

		$config['resolveType'] = function( $object ) use ( $config ) {
			$type = null;
			if ( is_callable( $config['resolveType'] ) ) {
				$type = call_user_func( $config['resolveType'], $object );
			}

			/**
			 * Filter the resolve type method for all interfaces
			 *
			 * @param mixed           $type   The Type to resolve to, based on the object being resolved.
			 * @param mixed           $object The Object being resolved.
			 * @param WPInterfaceType $wp_interface_type   The WPInterfaceType instance.
			 */
			return apply_filters( 'graphql_interface_resolve_type', $type, $object, $this );
		};

		/**
		 * Filter the config of WPInterfaceType
		 *
		 * @param array           $config Array of configuration options passed to the WPInterfaceType when instantiating a new type
		 * @param WPInterfaceType $wp_interface_type   The instance of the WPInterfaceType class
		 */
		$config = apply_filters( 'graphql_wp_interface_type_config', $config, $this );

		parent::__construct( $config );
	}

	/**
	 * This function sorts the fields and applies a filter to allow for easily
	 * extending/modifying the shape of the Schema for the type.
	 *
	 * @param array  $fields
	 * @param string $type_name
	 *
	 * @return mixed
	 * @since 0.0.5
	 */
	public function prepare_fields( array $fields, string $type_name ) {

		/**
		 * Filter all object fields, passing the $typename as a param
		 *
		 * This is useful when several different types need to be easily filtered at once. . .for example,
		 * if ALL types with a field of a certain name needed to be adjusted, or something to that tune
		 *
		 * @param array  $fields    The array of fields for the object config
		 * @param string $type_name The name of the object type
		 */
		$fields = apply_filters( 'graphql_interface_fields', $fields, $type_name );

		/**
		 * Filter once with lowercase, once with uppercase for Back Compat.
		 */
		$lc_type_name = lcfirst( $type_name );
		$uc_type_name = ucfirst( $type_name );

		/**
		 * Filter the fields with the typename explicitly in the filter name
		 *
		 * This is useful for more targeted filtering, and is applied after the general filter, to allow for
		 * more specific overrides
		 *
		 * @param array $fields The array of fields for the object config
		 */
		$fields = apply_filters( "graphql_{$lc_type_name}_fields", $fields );

		/**
		 * Filter the fields with the typename explicitly in the filter name
		 *
		 * This is useful for more targeted filtering, and is applied after the general filter, to allow for
		 * more specific overrides
		 *
		 * @param array $fields The array of fields for the object config
		 */
		$fields = apply_filters( "graphql_{$uc_type_name}_fields", $fields );

		/**
		 * This sorts the fields alphabetically by the key, which is super handy for making the schema readable,
		 * as it ensures it's not output in just random order
		 */
		ksort( $fields );

		return $fields;
	}

}
