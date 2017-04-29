<?php
namespace WPGraphQL\Type\PostObject\Connection;

use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Utils\AST;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Types;

/**
 * Class PostObjectConnectionDefinition
 *
 * @package WPGraphQL\Type\Comment\Connection
 * @since   0.0.5
 */
class PostObjectConnectionDefinition {

	/**
	 * Stores some date for the Relay connection for post objects
	 *
	 * @var array $connection
	 * @since  0.0.5
	 * @access private
	 */
	private static $connection;

	/**
	 * Stores the definition for the debug fields that can be used for introspection into the query
	 *
	 * @var mixed|null|\WPGraphQL\Type\WPObjectType $debug_fields
	 */
	public static $debug_fields;

	/**
	 * Configures the debug_fields
	 *
	 * @param $connection_name
	 *
	 * @return mixed|null
	 */
	public static function debug_fields( $connection_name ) {

		if ( null === self::$debug_fields ) {
			self::$debug_fields = [];
		}

		if ( empty( self::$debug_fields[ $connection_name ] ) ) {

			self::$debug_fields[ $connection_name ] = new WPObjectType([
				'name' => $connection_name . 'ConnectionDebug',
				'fields' => [
					'queryRequest' => [
						'type' => Types::string(),
						'description' => __( 'The request used to query items. Useful for debugging.', 'wp-graphql' ),
					],
					'totalItems' => [
						'type' => Types::int(),
						'description' => __( 'The total items matching the query. (NOTE: Using this field can degrade performance.)', 'wp-graphql' ),
					],
				],
			]);
		}

		return ! empty( self::$debug_fields[ $connection_name ] ) ? self::$debug_fields[ $connection_name ] : null;

	}

	/**
	 * Method that sets up the relay connection for post objects
	 *
	 * @param object $post_type_object
	 *
	 * @return mixed
	 * @since 0.0.5
	 *
	 * @return mixed
	 */
	public static function connection( $post_type_object ) {

		if ( empty( $post_type_object->name ) ) {
			return null;
		}

		if ( null === self::$connection ) {
			self::$connection = [];
		}

		if ( empty( self::$connection[ $post_type_object->name ] ) ) {

			/**
			 * Setup the connectionDefinition
			 *
			 * @since 0.0.5
			 */
			$connection = Relay::connectionDefinitions( [
				'nodeType'         => Types::post_object( $post_type_object->name ),
				'name'             => $post_type_object->graphql_plural_name,
				'connectionFields' => function() use ( $post_type_object ) {
					return [
						'postTypeInfo' => [
							'type'        => Types::post_type(),
							'description' => __( 'Information about the type of content being queried', 'wp-graphql' ),
							'resolve'     => function( $source, array $args, AppContext $context, ResolveInfo $info ) use ( $post_type_object ) {
								return $post_type_object;
							},
						],
						'debug' => [
							'type' => self::debug_fields( $post_type_object->graphql_plural_name ),
						],
					];
				},
			] );

			/**
			 * Add the "where" args to the postObjectConnections
			 *
			 * @since 0.0.5
			 */
			$args = [
				'where' => [
					'name' => 'where',
					'type' => Types::post_object_query_args(),
				],
			];

			/**
			 * Add the connection to the post_objects_connection object
			 *
			 * @since 0.0.5
			 */
			self::$connection[ $post_type_object->name ] = [
				'type'        => $connection['connectionType'],
				'description' => sprintf( __( 'A collection of %s objects', 'wp-graphql' ), $post_type_object->graphql_plural_name ),
				'args'        => array_merge( Relay::connectionArgs(), $args ),
				'resolve'     => function( $source, array $args, AppContext $context, ResolveInfo $info ) use ( $post_type_object ) {
					return DataSource::resolve_post_objects_connection( $source, $args, $context, $info, $post_type_object->name );
				},
			];
		}

		/**
		 * Return the connection from the post_objects_connection object
		 *
		 * @since 0.0.5
		 */
		return self::$connection[ $post_type_object->name ];

	}

}
