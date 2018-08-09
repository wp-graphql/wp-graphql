<?php

namespace WPGraphQL\Type;

use GraphQL\Type\Definition\InterfaceType;

/**
 * Class WidgetType
 *
 * @package WPGraphQL\Type\Widget
 * @since   0.0.31
 */
class WPInterfaceType extends InterfaceType {
  
  /**
	 * This holds the unregistered types definitions
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
    do_action( 'graphql_wp_object_type', $config, $this );

    parent::__construct( $config );
    
  }
  
  /**
	 * prepare_types
	 *
	 * This function sorts the types and filters the schema config to use interface child objects not included on the type registry.
	 *
	 * @param array  $types - interface child types
	 *
	 * @return mixed
	 * @since 0.0.31
	 */
  public static function prepare_types( $types, $type_name ) {

    /**
			 * Filter once with lowercase, once with uppercase for Back Compat.
			 */
			$lc_type_name = lcfirst( $type_name );
			$uc_type_name = ucfirst( $type_name );

			/**
			 * Filter the types with the type_name explicitly in the filter name
			 *
			 * This is useful for more targeted filtering, and is applied after the general filter, to allow for
			 * more specific overrides
			 *
			 * @param array $types The array of types for the schema config
			 */
			$types = apply_filters( "graphql_{$lc_type_name}_types", $types );

			/**
			 * Filter the types with the type_name explicitly in the filter name
			 *
			 * This is useful for more targeted filtering, and is applied after the general filter, to allow for
			 * more specific overrides
			 *
			 * @param array $types The array of types for the schema config
			 */
			$types = apply_filters( "graphql_{$uc_type_name}_types", $types );

    add_filter( 'graphql_schema_config', function( $schema ) use ( $types ) {

      if( is_array( $types ) && ! empty( $types ) ) { 

        /**
         * Merge $types array with schema config types array
         */
        if( ! empty( $schema['types'] ) ) $schema['types'] = array_merge( $schema['types'], $types );
        else $schema['types'] = $types;

      }
  
      return $schema;

    } );

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
	public static function prepare_fields( $fields, $type_name ) {

		if ( null === self::$prepared_fields ) {
			self::$prepared_fields = [];
		}

		if ( empty( self::$prepared_fields[ $type_name ] ) ) {
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
			 * @param array $fields The array of fields for the interface config
			 */
			$fields = apply_filters( "graphql_{$lc_type_name}_interface_fields", $fields );

			/**
			 * Filter the fields with the typename explicitly in the filter name
			 *
			 * This is useful for more targeted filtering, and is applied after the general filter, to allow for
			 * more specific overrides
			 *
			 * @param array $fields The array of fields for the interface config
			 */
			$fields = apply_filters( "graphql_{$uc_type_name}_interface_fields", $fields );

			/**
			 * This sorts the fields alphabetically by the key, which is super handy for making the schema readable,
			 * as it ensures it's not output in just random order
			 */
			ksort( $fields );
			self::$prepared_fields[ $type_name ] = $fields;
		}
		return ! empty( self::$prepared_fields[ $type_name ] ) ? self::$prepared_fields[ $type_name ] : null;
  }

}