<?php
namespace WPGraphQL\Type\TermObject\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

/**
 * Class TermObjectConnectionDefinition
 * @package WPGraphQL\Type\Comment\Connection
 * @since 0.0.5
 */
class TermObjectConnectionDefinition {

	/**
	 * Stores some date for the Relay connection for term objects
	 *
	 * @var array $connection
	 * @since  0.0.5
	 * @access private
	 */
	private static $connection;

	/**
	 * Method that sets up the relay connection for term objects
	 *
	 * @param object $taxonomy_object
	 * @return mixed
	 * @since 0.0.5
	 */
	public static function connection( $taxonomy_object ) {

		if ( empty( $taxonomy_object->name ) ) {
			return;
		}

		if ( null === self::$connection ) {
			self::$connection = [];
		}

		if ( empty( self::$connection[ $taxonomy_object->name ] ) ) {

			/**
			 * Setup the connectionDefinition
			 * @since 0.0.5
			 */
			$connection = Relay::connectionDefinitions( [
				'nodeType' => Types::term_object( $taxonomy_object->name ),
				'name' => $taxonomy_object->graphql_plural_name,
				'connectionFields' => function() use ( $taxonomy_object ) {
					return [
						'taxonomyInfo' => [
							'type' => Types::taxonomy(),
							'description' => __( 'Information about the type of content being queried', 'wp-graphql' ),
							'resolve' => function( $source, array $args, $context, ResolveInfo $info ) use ( $taxonomy_object ) {
								return $taxonomy_object;
							},
						],
					];
				},
			] );

			/**
			 * Add the "where" args to the termObjectConnections
			 * @since 0.0.5
			 */
			$args = [
				'where' => [
					'name' => 'where',
					'type' => Types::term_object_query_args(),
				],
			];

			/**
			 * Add the connection to the post_objects_connection object
			 * @since 0.0.5
			 */
			self::$connection[ $taxonomy_object->name ] = [
				'type' => $connection['connectionType'],
				'description' => sprintf( __( 'A collection of %s objects', 'wp-graphql' ), $taxonomy_object->graphql_plural_name ),
				'args' => array_merge( Relay::connectionArgs(), $args ),
				'resolve' => function( $source, $args, $context, ResolveInfo $info ) use ( $taxonomy_object ) {
					return DataSource::resolve_term_objects_connection( $taxonomy_object->name, $source, $args, $context, $info );
				},
			];
		}

		return self::$connection[ $taxonomy_object->name ];

	}

}
