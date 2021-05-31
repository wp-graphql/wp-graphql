<?php

namespace WPGraphQL\Type;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ObjectType;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Registry\TypeRegistry;
use WPGraphQL\Type\InterfaceType\Node;

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

	/**
	 * Holds the node_interface definition allowing WPObjectTypes
	 * to easily define themselves as a node type by implementing
	 * self::$node_interface
	 *
	 * @var array|Node $node_interface
	 * @since 0.0.5
	 */
	private static $node_interface;

	/**
	 * Instance of the Type Registry
	 *
	 * @var TypeRegistry
	 */
	public $type_registry;

	/**
	 * WPObjectType constructor.
	 *
	 * @param array        $config
	 * @param TypeRegistry $type_registry
	 *
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
		 * @param WPObjectType $wp_object_type The instance of the WPObjectType class
		 */
		$config = apply_filters( 'graphql_wp_object_type_config', $config, $this );

		/**
		 * Set the Types to start with capitals
		 */
		$name           = ucfirst( $config['name'] );
		$config['name'] = apply_filters( 'graphql_type_name', $name, $config, $this );

		$interfaces = isset( $config['interfaces'] ) ? $config['interfaces'] : [];

		/**
		 * Filters the interfaces applied to an object type
		 *
		 * @param array        $interfaces     List of interfaces applied to the Object Type
		 * @param array        $config         The config for the Object Type
		 * @param WPObjectType $wp_object_type The WPObjectType instance
		 */
		$interfaces               = apply_filters( 'graphql_object_type_interfaces', $interfaces, $config, $this );
		$config['interfaceNames'] = $interfaces;

		/**
		 * Convert Interfaces from Strings to Types
		 */
		$config['interfaces'] = function() use ( $interfaces ) {
			$new_interfaces = [];
			if ( ! is_array( $interfaces ) ) {
				// TODO Throw an error.
				return $new_interfaces;
			}

			foreach ( $interfaces as $interface ) {
				if ( is_string( $interface ) ) {
					$new_interfaces[ $interface ] = $this->type_registry->get_type( $interface );
					continue;
				}
				if ( $interface instanceof WPInterfaceType ) {
					$new_interfaces[ get_class( $interface ) ] = $interface;
				}
			}

			return $new_interfaces;
		};

		/**
		 * Setup the fields
		 *
		 * @return array|mixed
		 */
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

				foreach ( $config['interfaceNames'] as $interface_name ) {
					$interface_type = null;
					if ( is_string( $interface_name ) ) {
						$interface_type = $this->type_registry->get_type( $interface_name );
					} elseif ( $interface_name instanceof WPInterfaceType ) {
						$interface_type = $interface_name;
					}
					$interface_fields = [];
					if ( ! empty( $interface_type ) && $interface_type instanceof WPInterfaceType ) {
						$interface_config_fields = $interface_type->getFields();
						foreach ( $interface_config_fields as $interface_field ) {
							$interface_fields[ $interface_field->name ] = $interface_field->config;
						}
					}

					$fields = array_replace_recursive( $interface_fields, $fields );
				}
			}

			$fields = $this->prepare_fields( $fields, $config['name'], $config );
			$fields = $this->type_registry->prepare_fields( $fields, $config['name'] );

			return $fields;
		};

		/**
		 * Run an action when the WPObjectType is instantiating
		 *
		 * @param array        $config         Array of configuration options passed to the WPObjectType when instantiating a new type
		 * @param WPObjectType $wp_object_type The instance of the WPObjectType class
		 */
		do_action( 'graphql_wp_object_type', $config, $this );

		parent::__construct( $config );
	}

	/**
	 * Node_interface
	 *
	 * This returns the node_interface definition allowing
	 * WPObjectTypes to easily implement the node_interface
	 *
	 * @return array|Node
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
		 * @param WPObjectType $wp_object_type The WPObjectType Class
		 * @param TypeRegistry $type_registry  The Type Registry
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
		 * @param WPObjectType $wp_object_type The WPObjectType Class
		 * @param TypeRegistry $type_registry  The Type Registry
		 */
		$fields = apply_filters( "graphql_{$lc_type_name}_fields", $fields, $this, $this->type_registry );

		/**
		 * Filter the fields with the typename explicitly in the filter name
		 *
		 * This is useful for more targeted filtering, and is applied after the general filter, to allow for
		 * more specific overrides
		 *
		 * @param array        $fields         The array of fields for the object config
		 * @param WPObjectType $wp_object_type The WPObjectType Class
		 * @param TypeRegistry $type_registry  The Type Registry
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
