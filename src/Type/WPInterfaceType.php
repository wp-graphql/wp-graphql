<?php

namespace WPGraphQL\Type;

use GraphQL\Type\Definition\InterfaceType;
use WPGraphQL\Data\DataSource;

/**
 * Class WPInterfaceType
 * 
 * Object Types should extend this class to take advantage of the helper methods
 * and consistent filters.
 * 
 * @package WPGraphQL\Type
 * @since   0.1.1
 */
class WPInterfaceType extends InterfaceType {
  
  	/**
	 * Holds the $prepared_fields definition for the WPInterfaceType
	 *
	 * @var array $prepared_types
	 */
	private static $prepared_fields;

	/**
	 * WPInterfaceType constructor.
	 */
	public function __construct( $config ) {
    
    	/**
		 * Set the Types to start with capitals
		 */
		$config['name'] = ucfirst( $config['name'] );

		/**
		 * Filter the config of WPInterfaceType
		 *
		 * @param array $config Array of configuration options passed to the WPInterfaceType when instantiating a new type
		 * @param Object $this The instance of the WPInterfaceType class
		 */
		$config = apply_filters( 'graphql_wp_interface_type_config', $config, $this );

		/**
		 * Run an action when the WPInterfaceType is instantiating
		 *
		 * @param array $config Array of configuration options passed to the WPInterfaceType when instantiating a new type
		 * @param Object $this The instance of the WPInterfaceType class
		 */
		do_action( 'graphql_wp_interface_type', $config, $this );

		parent::__construct( $config );
		
	}
	  
	/**
	 * prepare_interfaces
	 * 
	 * This function applies a filter to allow for easily
	 * extending/modifying the shape of the Schema for the type.
	 *
	 * @param array $interfaces - interface definition
	 * @param string $type_name	- object type name
	 * @return array
	 */
	public static function prepare_interfaces( $interfaces, $type_name ) {
		/**
		 * Filter once with lowercase, once with uppercase for Back Compat.
		 */
		$lc_type_name = lcfirst( $type_name );
		$uc_type_name = ucfirst( $type_name );

		/**
		 * Filter the interfaces with the typename explicitly in the filter name
		 *
		 * This is useful for more targeted filtering, and is applied after the general filter, to allow for
		 * more specific overrides
		 *
		 * @param array $interfaces The array of intefaces for the object config
		 */
		$prepared_interfaces = apply_filters( "graphql_{$lc_type_name}_interfaces", $interfaces );

		/**
		 * Filter the interfaces with the typename explicitly in the filter name
		 *
		 * This is useful for more targeted filtering, and is applied after the general filter, to allow for
		 * more specific overrides
		 *
		 * @param array $interfaces The array of interfaces for the object config
		 */
		$prepared_interfaces = apply_filters( "graphql_{$uc_type_name}_interfaces", $interfaces );
	
		return $prepared_interfaces;
	}

}