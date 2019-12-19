<?php

namespace WPGraphQL\Type;

use GraphQL\Type\Definition\InterfaceType;
use WPGraphQL\Registry\TypeRegistry;

class WPInterfaceType extends InterfaceType {

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
	 * @access public
	 */
	public function __construct( array $config, TypeRegistry $type_registry ) {

		$this->type_registry = $type_registry;

		$config['fields'] = function() use ( $config ) {
			$fields = $this->prepare_fields( $config['fields'], $config['name'] );
			$fields = $this->type_registry->prepare_fields( $fields, $config['name'] );

			return $fields;
		};

		/**
		 * Filter the config of WPInterfaceType
		 *
		 * @param array           $config Array of configuration options passed to the WPInterfaceType when instantiating a new type
		 * @param WPInterfaceType $this   The instance of the WPObjectType class
		 */
		$config = apply_filters( 'graphql_wp_interface_type_config', $config, $this );

		parent::__construct( $config );
	}

	/**
	 * prepare_fields
	 *
	 * This function sorts the fields and applies a filter to allow for easily
	 * extending/modifying the shape of the Schema for the type.
	 *
	 * @param array  $fields
	 * @param string $type_name
	 *
	 * @return mixed
	 * @since 0.0.5
	 */
	public function prepare_fields( $fields, $type_name ) {

		/**
		 * Filter all object fields, passing the $typename as a param
		 *
		 * This is useful when several different types need to be easily filtered at once. . .for example,
		 * if ALL types with a field of a certain name needed to be adjusted, or something to that tune
		 *
		 * @param array        $fields        The array of fields for the object config
		 * @param string       $type_name     The name of the object type
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
