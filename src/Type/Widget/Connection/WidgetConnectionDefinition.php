<?php

namespace WPGraphQL\Type\Widget\Connection;

use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;
use WPGraphQL\Type\WPInputObjectType;

/**
 * Class WidgetConnectionDefinition
 *
 * @package WPGraphQL\Type\Widget\Connection
 * @since   0.0.31
 */
class WidgetConnectionDefinition {

	/**
	 * Stores the Relay connection for Widget
	 *
	 * @var array $connection
	 * @access private
	 */
	private static $connection = [];

	/**
	 * Stores the where_args Input Object Type
	 *
	 * @var \WPGraphQL\Type\WPInputObjectType $where_args
	 */
	private static $where_args;

	/**
	 * Stores the fields for the $where_args
	 *
	 * @var array $where_fields
	 */
	private static $where_fields;

	/**
	 * Create the Relay connection for Widgets.
	 *
	 * @param string $from_type Connection type.
	 * @return mixed
	 * @since  0.0.31
	 */
	public static function connection( $from_type = 'Root' ) {

		if ( empty( self::$connection[ $from_type ] ) ) {

			$type_name = 'Widgets';

			$connection = Relay::connectionDefinitions( [
				'nodeType' => Types::widget(),
				'name'     => ucfirst( $from_type ) . $type_name,
				'connectionFields' => function() {
					return [
						'nodes' => [
							'type'        => Types::list_of( Types::widget() ),
							'description' => __( 'The nodes of the connection, without the edges', 'wp-graphql' ),
							'resolve'     => function( $source, $args, $context, $info ) {
								return ! empty( $source['nodes'] ) ? $source['nodes'] : [];
							},
						],
					];
				},
			] );

			$args = [
				'where' => [
					'name' => 'where',
					'type' => self::where_args( $type_name ),
				],
			];

			/**
			 * Add the connection to the widgets_connection object
			 *
			 * @since 0.0.31
			 */
			$connection_name 								= ucfirst( $from_type ) . $type_name;
			self::$connection[ $from_type ] = [
				'type'        => $connection['connectionType'],
				// Translators: the placeholder is the name of the post_type
				'description' => sprintf( __( 'A collection of %s objects', 'wp-graphql' ), $type_name ),
				'args'        => array_merge( Relay::connectionArgs(), $args ),
				'resolve'     => function( $source, $args, $context, $info ) {
					return DataSource::resolve_widgets_connection( $source, $args, $context, $info );
				},
			];
		}

		/**
		 * Return the connection from the post_objects_connection object
		 *
		 * @since 0.0.31
		 */
		return self::$connection[ $from_type ];
	}

	/**
	 * Defines the "where" args that can be used to query menuItems
	 *
	 * @return WPInputObjectType
	 */
	private static function where_args( $type_name ) {

		if ( null === self::$where_args ) {
			
			self::$where_args = new WPInputObjectType( [
				'name'   => $type_name . 'QueryArgs',
				'fields' => function() use ( $type_name ) {
					return self::where_fields( $type_name . 'QueryArgs' );
				},
			] );
		}

		return ! empty( self::$where_args ) ? self::$where_args : null;

	}

	/**
	 * This defines the fields to be used in the $where_args input type
	 *
	 * @return array|mixed
	 */
	private static function where_fields( $type_name ) {
		if ( null === self::$where_fields ) {
			$fields = [
				'id' => [
					'type'        => Types::int(),
					'description' => __( 'The instance ID of the widget', 'wp-graphql' ),
				],
				'name' => [
					'type'        => Types::string(),
					'description' => __( 'Display name of the widget', 'wp-graphql' ),
				],
				'basename' => [
					'type'        => Types::string(),
					'description' => __( 'Display name of the widget', 'wp-graphql' ),
				],
			];

			self::$where_fields = WPInputObjectType::prepare_fields( $fields, $type_name );
		}

		return self::$where_fields;
	}

}