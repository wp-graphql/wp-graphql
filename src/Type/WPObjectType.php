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
 * @since 0.0.5
 */
class WPObjectType extends ObjectType {

	/**
	 * Holds the $prepared_fields definition for the PostObjectType
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
	 * @since 0.0.5
	 */
	public function __construct( $config ) {
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
			$node_interface = DataSource::get_node_definition();
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
	 * @param array $fields
	 * @param string $type_name
	 * @return mixed
	 * @since 0.0.5
	 */
	public static function prepare_fields( $fields, $type_name ) {

		if ( null === self::$prepared_fields ) {
			self::$prepared_fields = [];
		}

		if ( empty( self::$prepared_fields[ $type_name ] ) ) {
			$fields = apply_filters( "graphql_{$type_name}_fields", $fields );
			ksort( $fields );
			self::$prepared_fields[ $type_name ] = $fields;
		}

		return ! empty( self::$prepared_fields[ $type_name ] ) ? self::$prepared_fields[ $type_name ] : null;

	}

}
