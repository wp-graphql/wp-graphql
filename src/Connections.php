<?php
namespace WPGraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;

/**
 * Class Connections
 * @package WPGraphQL
 */
class Connections {

	/**
	 * @var array comments_connection
	 * @since 0.0.5
	 */
	protected static $comments_connection;

	/**
	 * @var array post_objects_connection
	 * @since 0.0.5
	 */
	protected static $post_objects_connection;

	/**
	 * @var array term_objects_connection
	 * @since 0.0.5
	 */
	protected static $term_objects_connection;

	/**
	 * @var array users_connection
	 * @since 0.0.5
	 */
	protected static $users_connection;

	/**
	 * comments_connection
	 *
	 * This sets up a connection of comments
	 *
	 * @return mixed
	 * @since 0.0.5
	 */
	public static function comments_connection() {

		if ( null === self::$comments_connection ) {
			$comments_connection = Relay::connectionDefinitions( [
				'nodeType' => Types::comment(),
				'name'     => 'comments',
			] );

			self::$comments_connection = [
				'type'        => $comments_connection['connectionType'],
				'description' => sprintf( __( 'A collection of comment objects', 'wp-graphql' ) ),
				'args'        => Relay::connectionArgs(),
				'resolve'     => function( $source, $args, $context, ResolveInfo $info ) {
					return DataSource::resolve_comments( $source, $args, $context, $info );
				},
			];

		}

		return self::$comments_connection;
	}

	/**
	 * post_objects_connection
	 *
	 * This sets up a connection to posts (of a specified post_type).
	 * This establishes the Relay connection specs, setting up the edges/node/cursor structure.
	 *
	 * @param $post_type_object
	 * @return mixed
	 */
	public static function post_objects_connection( $post_type_object ) {

		if ( null === self::$post_objects_connection->{ $post_type_object->name } ) {
			/**
			 * Setup the connectionDefinition
			 * @since 0.0.5
			 */
			$connection = Relay::connectionDefinitions( [
				'nodeType' => Types::post_object( $post_type_object->name ),
				'name'     => $post_type_object->graphql_plural_name,
			] );

			/**
			 * Add the "where" args to the postObjectConnections
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
			 * @since 0.0.5
			 */
			self::$post_objects_connection->{ $post_type_object->name } = [
				'type'        => $connection['connectionType'],
				'description' => sprintf( __( 'A collection of % objects', 'wp-graphql' ), $post_type_object->graphql_plural_name ),
				'args'        => array_merge( Relay::connectionArgs(), $args ),
				'resolve'     => function( $source, $args, $context, ResolveInfo $info ) use ( $post_type_object ) {
					return DataSource::resolve_post_objects_connection( $post_type_object->name, $source, $args, $context, $info );
				},
			];
		}

		/**
		 * Return the connection from the post_objects_connection object
		 * @since 0.0.5
		 */
		return self::$post_objects_connection->{ $post_type_object->name };
	}


	/**
	 * term_objects_connection
	 *
	 * This sets up a connection to posts (of a specified taxonomy).
	 * This establishes the Relay connection specs, setting up the edges/node/cursor structure.
	 *
	 * @param $taxonomy_object
	 * @return mixed
	 * @since 0.0.5
	 */
	public static function term_objects_connection( $taxonomy_object ) {

		if ( null === self::$term_objects_connection->{ $taxonomy_object->name } ) {

			$connection = Relay::connectionDefinitions( [
				'nodeType' => Types::term_object( $taxonomy_object->name ),
				'name'     => $taxonomy_object->graphql_plural_name,
			] );

			self::$term_objects_connection->{ $taxonomy_object->name } = [
				'type'        => $connection['connectionType'],
				'description' => sprintf( __( 'A collection of % objects', 'wp-graphql' ), $taxonomy_object->graphql_plural_name ),
				'args'        => Relay::connectionArgs(),
				'resolve'     => function( $source, $args, $context, ResolveInfo $info ) use ( $taxonomy_object ) {
					return DataSource::resolve_term_objects_connection( $taxonomy_object->name, $source, $args, $context, $info );
				},
			];
		}

		return self::$term_objects_connection->{ $taxonomy_object->name };

	}

	/**
	 * @return array
	 */
	public static function users_connection() {

		if ( null === self::$users_connection ) {
			$users_connection = Relay::connectionDefinitions( [
				'nodeType' => Types::user(),
			] );

			self::$users_connection = $users_connection;
		}

		return self::$users_connection;

	}

}