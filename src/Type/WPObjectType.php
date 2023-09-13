<?php

namespace WPGraphQL\Type;

use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class WPObjectType
 *
 * Object Types should extend this class to take advantage of the helper methods
 * and consistent filters.
 *
 * @package WPGraphQL\Type
 * @since   0.0.5
 */
class WPObjectType extends ObjectType {

	use WPInterfaceTrait;

	/**
	 * Holds the node_interface definition allowing WPObjectTypes
	 * to easily define themselves as a node type by implementing
	 * self::$node_interface
	 *
	 * @var array|\WPGraphQL\Type\InterfaceType\Node $node_interface
	 * @since 0.0.5
	 */
	private static $node_interface;

	/**
	 * Instance of the Type Registry
	 *
	 * @var \WPGraphQL\Registry\TypeRegistry
	 */
	public $type_registry;

	/**
	 * @var array
	 */
	public $config;

	/**
	 * WPObjectType constructor.
	 *
	 * @param array        $config
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @throws \Exception
	 * @since 0.0.5
	 */
	public function __construct( $config, TypeRegistry $type_registry ) {

		/**
		 * Get the Type Registry
		 */
		$this->type_registry = $type_registry;

		/**
		 * Filter the config of WPObjectType
		 *
		 * @param array        $config         Array of configuration options passed to the WPObjectType when instantiating a new type
		 * @param \WPGraphQL\Type\WPObjectType $wp_object_type The instance of the WPObjectType class
		 */
		$config = apply_filters( 'graphql_wp_object_type_config', $config, $this );

		$this->config = $config;

		/**
		 * Set the Types to start with capitals
		 */
		$name           = ucfirst( $config['name'] );
		$config['name'] = apply_filters( 'graphql_type_name', $name, $config, $this );

		/**
		 * Setup the fields
		 *
		 * @return array|mixed
		 */
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
						$interface_fields[ $interface_field_name ] = $interface_field->config;
					}
				}
			}

			if ( ! empty( $interface_fields ) ) {
				$fields = array_replace_recursive( $interface_fields, $fields );
			}

			$fields = $this->prepare_fields( $fields, $config['name'], $config );
			$fields = $this->type_registry->prepare_fields( $fields, $config['name'] );

			return $fields;
		};

		/**
		 * Run an action when the WPObjectType is instantiating
		 *
		 * @param array        $config         Array of configuration options passed to the WPObjectType when instantiating a new type
		 * @param \WPGraphQL\Type\WPObjectType $wp_object_type The instance of the WPObjectType class
		 */
		do_action( 'graphql_wp_object_type', $config, $this );

		parent::__construct( $config );
	}

	/**
	 * Get the interfaces implemented by the ObjectType
	 *
	 * @return array
	 */
	public function getInterfaces(): array {
		return $this->get_implemented_interfaces();
	}

	/**
	 * Node_interface
	 *
	 * This returns the node_interface definition allowing
	 * WPObjectTypes to easily implement the node_interface
	 *
	 * @return array|\WPGraphQL\Type\InterfaceType\Node
	 * @since 0.0.5
	 */
	public static function node_interface() {
		if ( null === self::$node_interface ) {
			$node_interface       = DataSource::get_node_definition();
			self::$node_interface = $node_interface['nodeInterface'];
		}

		return self::$node_interface;
	}

	/**
	 * This function sorts the fields and applies a filter to allow for easily
	 * extending/modifying the shape of the Schema for the type.
	 *
	 * @param array  $fields
	 * @param string $type_name
	 * @param array  $config
	 *
	 * @return mixed
	 * @since 0.0.5
	 */
	public function prepare_fields( $fields, $type_name, $config ) {

		/**
		 * Filter all object fields, passing the $typename as a param
		 *
		 * This is useful when several different types need to be easily filtered at once. . .for example,
		 * if ALL types with a field of a certain name needed to be adjusted, or something to that tune
		 *
		 * @param array        $fields         The array of fields for the object config
		 * @param string       $type_name      The name of the object type
		 * @param \WPGraphQL\Type\WPObjectType $wp_object_type The WPObjectType Class
		 * @param \WPGraphQL\Registry\TypeRegistry $type_registry The Type Registry
		 */
		$fields = apply_filters( 'graphql_object_fields', $fields, $type_name, $this, $this->type_registry );

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
		 * @param array        $fields         The array of fields for the object config
		 * @param \WPGraphQL\Type\WPObjectType $wp_object_type The WPObjectType Class
		 * @param \WPGraphQL\Registry\TypeRegistry $type_registry The Type Registry
		 */
		$fields = apply_filters( "graphql_{$lc_type_name}_fields", $fields, $this, $this->type_registry );

		/**
		 * Filter the fields with the typename explicitly in the filter name
		 *
		 * This is useful for more targeted filtering, and is applied after the general filter, to allow for
		 * more specific overrides
		 *
		 * @param array        $fields         The array of fields for the object config
		 * @param \WPGraphQL\Type\WPObjectType $wp_object_type The WPObjectType Class
		 * @param \WPGraphQL\Registry\TypeRegistry $type_registry The Type Registry
		 */
		$fields = apply_filters( "graphql_{$uc_type_name}_fields", $fields, $this, $this->type_registry );

		/**
		 * This sorts the fields alphabetically by the key, which is super handy for making the schema readable,
		 * as it ensures it's not output in just random order
		 */
		ksort( $fields );

		return $fields;
	}
}
