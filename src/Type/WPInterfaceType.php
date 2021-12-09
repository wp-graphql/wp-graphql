<?php

namespace WPGraphQL\Type;

use Exception;
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
	 * @var array
	 */
	public $config;

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

		$this->config = $config;

		$name             = ucfirst( $config['name'] );
		$config['name']   = apply_filters( 'graphql_type_name', $name, $config, $this );
		$config['fields'] = function () use ( $config ) {

			$fields = $config['fields'];

			/**
			 * Get the fields of interfaces and ensure they exist as fields of this type.
			 *
			 * Types are still responsible for ensuring the fields resolve properly.
			 */
			if ( ! empty( $this->getInterfaces() ) && is_array( $this->getInterfaces() ) ) {

				$interface_fields = [];

				foreach ( $this->getInterfaces() as $interface_type ) {

					if ( ! $interface_type instanceof InterfaceType ) {
						$interface_type = $this->type_registry->get_type( $interface_type );
					}

					if ( ! $interface_type instanceof InterfaceType ) {
						continue;
					}

					$interface_config_fields = $interface_type->getFields();

					if ( empty( $interface_config_fields ) || ! is_array( $interface_config_fields ) ) {
						continue;
					}

					foreach ( $interface_config_fields as $interface_field_name => $interface_field ) {
						if ( ! isset( $interface_field->config ) ) {
							continue;
						}

						$interface_fields[ $interface_field_name ] = $interface_field->config;
					}
				}
			}

			if ( ! empty( $interface_fields ) ) {
				$fields = array_replace_recursive( $interface_fields, $fields );
			}

			$fields = $this->prepare_fields( $fields, $config['name'] );
			$fields = $this->type_registry->prepare_fields( $fields, $config['name'] );

			return $fields;
		};

		$config['resolveType'] = function ( $object ) use ( $config ) {
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
	 * Get interfaces implemented by this Interface
	 *
	 * @return array
	 */
	public function getInterfaces(): array {
		return $this->get_implemented_interfaces();
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
