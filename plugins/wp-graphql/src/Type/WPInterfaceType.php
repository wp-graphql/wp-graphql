<?php

namespace WPGraphQL\Type;

use GraphQL\Type\Definition\InterfaceType;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class WPInterface
 *
 * @phpstan-import-type InterfaceConfig from \GraphQL\Type\Definition\InterfaceType
 */
class WPInterfaceType extends InterfaceType {

	use WPInterfaceTrait;

	/**
	 * Instance of the TypeRegistry as an Interface needs knowledge of available Types
	 *
	 * @var \WPGraphQL\Registry\TypeRegistry
	 */
	public $type_registry;

	/**
	 * @var array<string, array<string, mixed>>
	 */
	public $fields;

	/**
	 * @var \GraphQL\Type\Definition\InterfaceType[]
	 */
	public $interfaces = [];

	/**
	 * WPInterfaceType constructor.
	 *
	 * @param array<string,mixed>              $config The configuration array for setting up the WPInterfaceType.
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @phpstan-param InterfaceConfig $config
	 *
	 * @throws \Exception
	 */
	public function __construct( array $config, TypeRegistry $type_registry ) {
		$this->type_registry = $type_registry;

		$this->config = $config;

		$name                 = ! empty( $config['name'] ) ? ucfirst( $config['name'] ) : $this->inferName();
		$config['name']       = apply_filters( 'graphql_type_name', $name, $config, $this );
		$config['fields']     = function () use ( $config ) {
			return ! empty( $this->fields ) ? $this->fields : $this->get_fields( $config, $this->type_registry );
		};
		$config['interfaces'] = $this->getInterfaces();

		$config['resolveType'] = function ( $obj, $context, $info ) use ( $config ) {
			$type = null;
			if ( isset( $config['resolveType'] ) && is_callable( $config['resolveType'] ) ) {
				$type = call_user_func( $config['resolveType'], $obj, $context, $info );
			}

			/**
			 * Filter the resolve type method for all interfaces
			 *
			 * @param mixed $type The Type to resolve to, based on the object being resolved.
			 * @param mixed $obj  The Object being resolved.
			 * @param \WPGraphQL\Type\WPInterfaceType $wp_interface_type The WPInterfaceType instance.
			 */
			return apply_filters( 'graphql_interface_resolve_type', $type, $obj, $this );
		};

		/**
		 * Filter the config of WPInterfaceType
		 *
		 * @param InterfaceConfig                 $config Array of configuration options passed to the WPInterfaceType when instantiating a new type
		 * @param \WPGraphQL\Type\WPInterfaceType $wp_interface_type The instance of the WPInterfaceType class
		 */
		$config = apply_filters( 'graphql_wp_interface_type_config', $config, $this );

		parent::__construct( $config );
	}

	/**
	 * Get the interfaces implemented by the ObjectType
	 *
	 * @return \GraphQL\Type\Definition\InterfaceType[]
	 */
	public function getInterfaces(): array {
		if ( ! empty( $this->interfaces ) ) {
			return $this->interfaces;
		}
		$this->interfaces = $this->get_implemented_interfaces();
		return $this->interfaces;
	}

	/**
	 * This function sorts the fields and applies a filter to allow for easily
	 * extending/modifying the shape of the Schema for the type.
	 *
	 * @param array<string,array<string,mixed>> $fields The array of fields for the object config
	 * @param string                            $type_name The name of the type to prepare fields for
	 * @param array<string,mixed>               $config    The config for the Object Type
	 * @return array<string,array<string,mixed>>
	 * @since 0.0.5
	 */
	public function prepare_fields( array $fields, string $type_name, array $config ): array {

		/**
		 * Filter all interface fields, passing the $typename as a param
		 *
		 * This is useful when several different types need to be easily filtered at once. . .for example,
		 * if ALL types with a field of a certain name needed to be adjusted, or something to that tune
		 *
		 * @param array<string,array<string,mixed>> $fields    The array of fields for the object config
		 * @param string                            $type_name The name of the object type
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
		 * @param array<string,array<string,mixed>> $fields The array of fields for the object config
		 */
		$fields = apply_filters( "graphql_{$lc_type_name}_fields", $fields );

		/**
		 * Filter the fields with the typename explicitly in the filter name
		 *
		 * This is useful for more targeted filtering, and is applied after the general filter, to allow for
		 * more specific overrides
		 *
		 * @param array<string,array<string,mixed>> $fields The array of fields for the object config
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
