<?php
namespace WPGraphQL\Type;

/**
 * Class TypeRegistry
 *
 * This class provides some helper methods to make creating type registries easier.
 *
 * @package WPGraphQL\Registry
 */
abstract class TypeRegistryInterface {

  /**
   * Store type registry name
   *
   * @var string
   */
  protected static $registry_name;

  /**
   * Store type fully qualified class name
   *
   * @var string
   */
  protected static $__CLASS__;  

  /**
   * Stores the type objects
	 *
	 * @var array $types
	 * @since  0.0.31
	 * @access private
	 */
  protected static $types;

  /**
   * TypeRegistryInterface constructor
   *
   * @return void
   */
  public function __construct( $from_type = 'root' ) {

    static::$registry_name = "{$from_type}_registry";

    add_filter('graphql_schema_config_types', function( $types ) {
      
      /**
       * Get registry types
       */
      $registry_types = self::prepare_types( static::get_types() );
      if( is_array( $registry_types ) && ! empty( $registry_types ) ) {
        $types = array_merge( $types, $registry_types );
      }

      return $types;
    });
  }

  /**
   * Return type listing for GraphQL Schema config types field. This is for using types
   * that aren't loaded on the root type registry
   *
   * @return array
   */
  abstract protected static function get_types();

  /**
   * Prepares Type config array
   *
   * @param string $type_name
   * @param array $data
   * @return array
   */
  abstract protected static function _config( $type_name, $data );

  /**
   * Workhorse of Type Registry - Initializes and retrieves Types
   *
   * @param string $type_name - Name of Type
   * @return mixed
   */
  public static function __callStatic( $type_name, array $args = [] ) {

    $registry_name = static::$registry_name;
    $__CLASS__ = static::$__CLASS__;
    
    if( null === self::$types ) self::$types = [];
    if( ! isset( self::$types[ $registry_name ] ) ) self::$types[ $registry_name ] = [];
    
    /**
     * Check if default type_name_config function exists and initialize type
     * the name format is type_name_config()
     */
    $type_name = str_replace([ '-', ' ' ], '_', $type_name);
    $type_func = "{$type_name}_config";
    if( method_exists( $__CLASS__, $type_func ) && ! self::loaded( $type_name ) ) {
      self::$types[ $registry_name ][ $type_name ] = static::$type_func();
    }
    
    /**
     * If no config function exist use _config instead
     */
    elseif( ! method_exists( $__CLASS__, $type_func ) && ! self::loaded( $type_name ) ) {
      self::$types[ $registry_name ][ $type_name ] = static::_config( $type_name, ...$args );
    }

    /**
     * Filter for providing a custom type configuration function
     */
    self::$types = apply_filters( "graphql_register_{$registry_name}::{$type_name}", self::$types, $registry_name, $type_name );
     
    /**
     * Check if type loaded or possibly unloaded 
     */
    $type = self::$types[ $registry_name ][ $type_name ];
    return $type;
  }

   /**
   * Checks if type is loaded
   *
   * @param string $type_name - Name of type
   * @return boolean
   */
  private static function loaded( $type_name ) {
    return isset( self::$types[ static::$registry_name ][ $type_name ] ) && self::$types[ static::$registry_name ][ $type_name ] instanceof WPObjectType;
  }

  /**
	 * Sort and filters types array
	 *
	 * @param array  $types - array of Types
	 *
	 * @return mixed
	 * @since 0.0.31
	 */
  public static function prepare_types( $types ) {

    /**
     * Filter once with lowercase, once with uppercase for Back Compat.
     */
    $lc_type_name = lcfirst( self::$registry_name );
    $uc_type_name = ucfirst( self::$registry_name );

    /**
     * Filter the types with the registry_name explicitly in the filter name
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

    return ! empty( $types ) ? $types : null;

  }

}