<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\ObjectType;
use WPGraphQL\Data\DataSource;

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
	 * Holds the $prepared_fields definition for the PostObjectType
	 *
	 * @var $fields
	 */
	private static $prepared_fields;

	/**
	 * Holds the node_interface definition allowing WPObjectTypes
	 * to easily define themselves as a node type by implementing
	 * self::$node_interface
	 *
	 * @var $node_interface
	 * @since 0.0.5
	 */
	private static $node_interface;

	/**
	 * WPObjectType constructor.
	 *
	 * @since 0.0.5
	 */
	public function __construct( $config ) {

		/**
		 * Filter the config of WPObjectType
		 *
		 * @param array $config Array of configuration options passed to the WPObjectType when instantiating a new type
		 * @param Object $this The instance of the WPObjectType class
		 */
		$config = apply_filters( 'graphql_wp_object_type_config', $config, $this );

		/**
		 * Run an action when the WPObjectType is instantiating
		 *
		 * @param array $config Array of configuration options passed to the WPObjectType when instantiating a new type
		 * @param Object $this The instance of the WPObjectType class
		 */
		do_action( 'graphql_wp_object_type', $config, $this );

		parent::__construct( $config );
	}

	/**
	 * node_interface
	 *
	 * This returns the node_interface definition allowing
	 * WPObjectTypes to easily implement the node_interface
	 *
	 * @return array|\WPGraphQL\Data\node_interface
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
	 * prepare_fields
	 *
	 * This function sorts the fields and applies a filter to allow for easily
	 * extending/modifying the shape of the Schema for the type.
	 *
	 * @param array  $fields The fields being prepared
	 * @param string $type_name The name of the type of object for which the fields being prepared
	 * @param object $type The Type object for which the fields are being prepared
	 *
	 * @return mixed
	 * @since 0.0.5
	 */
	public static function prepare_fields( $fields, $type_name, $type = null ) {

		if ( null === self::$prepared_fields ) {
			self::$prepared_fields = [];
		}

		if ( empty( self::$prepared_fields[ $type_name ] ) ) :
			/**
			 * Filter all object fields, passing the $typename as a param
			 *
			 * This is useful when several different types need to be easily filtered at once. . .for example,
			 * if ALL types with a field of a certain name needed to be adjusted, or something to that tune
			 *
			 * @param array $fields The array of fields for the object config
			 * @param string $type_name The name of the object type
			 * @param object $type The object type being filtered
			 */
			$fields = apply_filters( 'graphql_object_fields', $fields, $type_name, $type );

			/**
			 * Filter the fields with the typename explicitly in the filter name
			 *
			 * This is useful for more targeted filtering, and is applied after the general filter, to allow for
			 * more specific overrides
			 *
			 * @param array $fields The array of fields for the object config
			 * @param object $type The object type being filtered
			 */
			$fields = apply_filters( "graphql_{$type_name}_fields", $fields, $type );

			/**
			 * This sorts the fields alphabetically by the key, which is super handy for making the schema readable,
			 * as it ensures it's not output in just random order
			 */
			ksort( $fields );
			self::$prepared_fields[ $type_name ] = $fields;
		endif;
		return ! empty( self::$prepared_fields[ $type_name ] ) ? self::$prepared_fields[ $type_name ] : null;
	}

}
