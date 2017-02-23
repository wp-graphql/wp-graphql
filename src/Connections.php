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
	 * Stores some data for the Relay connection for comments
	 *
	 * @var array $comments_connection
	 * @since  0.0.5
	 * @access protected
	 */
	protected static $comments_connection;

	/**
	 * Stores some data for the Relay connection for plugins
	 *
	 * @var array plugins_connection
	 * @since  0.0.5
	 * @access protected
	 */
	protected static $plugins_connection;

	/**
	 * Stores some data for the Relay connection for post objects
	 *
	 * @var array post_objects_connection
	 * @since  0.0.5
	 * @access protected
	 */
	protected static $post_objects_connection;

	/**
	 * Stores some date for the Relay connection for post types
	 *
	 * @var array post_types_connection
	 * @since  0.0.5
	 * @access protected
	 */
	protected static $post_types_connection;

	/**
	 * Stores some date for the Relay connection for term objects
	 *
	 * @var array term_objects_connection
	 * @since  0.0.5
	 * @access protected
	 */
	protected static $term_objects_connection;

	/**
	 * Stores some date for the Relay connection for themes
	 *
	 * @var array themes_connection
	 * @since  0.0.5
	 * @access protected
	 */
	protected static $themes_connection;

	/**
	 * Stores some date for the Relay connection for post types
	 *
	 * @var array users_connection
	 * @since  0.0.5
	 * @access protected
	 */
	protected static $users_connection;

	/**
	 * Method that sets up the relay connection for comments
	 *
	 * @return array
	 * @since  0.0.5
	 * @access public
	 */
	public static function comments_connection() {

		if ( null === self::$comments_connection ) {
			$comments_connection = Relay::connectionDefinitions( [
				'nodeType' => Types::comment(),
				'name' => 'comments',
			] );

			/**
			 * Add the "where" args to the commentConnection
			 * @since 0.0.5
			 */
			$args = [
				'where' => [
					'name' => 'where',
					'type' => Types::comment_connection_query_args(),
				],
			];

			self::$comments_connection = [
				'type' => $comments_connection['connectionType'],
				'description' => __( 'A collection of comment objects', 'wp-graphql' ),
				'args' => array_merge( Relay::connectionArgs(), $args ),
				'resolve' => function( $source, $args, $context, ResolveInfo $info ) {
					return DataSource::resolve_comments_connection( $source, $args, $context, $info );
				},
			];

		}

		return self::$comments_connection;

	}

	/**
	 * Method that sets up the relay connection for plugins
	 *
	 * @return array
	 * @since  0.0.5
	 * @access public
	 */
	public static function plugins_connection() {

		if ( null === self::$plugins_connection ) {

			/**
			 * Setup the connectionDefinition
			 * @since 0.0.5
			 */
			$connection = Relay::connectionDefinitions( [
				'nodeType' => Types::plugin(),
				'name' => 'plugins',
			] );

			/**
			 * Add the connection to the post_objects_connection object
			 * @since 0.0.5
			 */
			self::$plugins_connection = [
				'type' => $connection['connectionType'],
				'description' => __( 'A collection of plugins', 'wp-graphql' ),
				'args' => Relay::connectionArgs(),
				'resolve' => function( $source, $args, $context, ResolveInfo $info ) {
					return DataSource::resolve_plugins_connection( $source, $args, $context, $info );
				},
			];

		}

		return self::$plugins_connection;

	}

	/**
	 * This sets up a connection to posts (of a specified post_type).
	 * This establishes the Relay connection specs, setting up the edges/node/cursor structure.
	 *
	 * @param \WP_Post_Type $post_type_object Post type object we want to make a connection for
	 * @return void|array
	 * @since  0.5.0
	 * @access public
	 */
	public static function post_objects_connection( $post_type_object ) {

		// Return early if the post_object passed does not have a name
		if ( empty( $post_type_object->name ) ) {
			return;
		}

		if ( null === self::$post_objects_connection ) {
			self::$post_objects_connection = [];
		}

		if ( empty( self::$post_objects_connection[ $post_type_object->name ] ) ) {

			/**
			 * Setup the connectionDefinition
			 * @since 0.0.5
			 */
			$connection = Relay::connectionDefinitions( [
				'nodeType' => Types::post_object( $post_type_object->name ),
				'name' => $post_type_object->graphql_plural_name,
				'connectionFields' => function() use ( $post_type_object ) {
					return [
						'postTypeInfo' => [
							'type' => Types::post_type(),
							'description' => __( 'Information about the type of content being queried', 'wp-graphql' ),
							'resolve' => function( $source, array $args, $context, ResolveInfo $info ) use ( $post_type_object ) {
								return $post_type_object;
							},
						],
					];
				},
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
			self::$post_objects_connection[ $post_type_object->name ] = [
				'type' => $connection['connectionType'],
				'description' => sprintf( __( 'A collection of %s objects', 'wp-graphql' ), $post_type_object->graphql_plural_name ),
				'args' => array_merge( Relay::connectionArgs(), $args ),
				'resolve' => function( $source, $args, $context, ResolveInfo $info ) use ( $post_type_object ) {
					return DataSource::resolve_post_objects_connection( $post_type_object->name, $source, $args, $context, $info );
				},
			];
		}

		/**
		 * Return the connection from the post_objects_connection object
		 * @since 0.0.5
		 */
		return self::$post_objects_connection[ $post_type_object->name ];

	}

	/**
	 * Method that sets up the relay connection for post types
	 *
	 * @return mixed
	 * @since  0.0.5
	 * @access public
	 */
	public static function post_types_connection() {

		if ( null === self::$post_types_connection ) {

			/**
			 * Setup the connectionDefinition
			 * @since 0.0.5
			 */
			$connection = Relay::connectionDefinitions( [
				'nodeType' => Types::post_type(),
				'name' => 'postTypes',
			] );

			/**
			 * Add the connection to the post_objects_connection object
			 * @since 0.0.5
			 */
			self::$post_types_connection = [
				'type' => $connection['connectionType'],
				'description' => __( 'A collection of post_type objects', 'wp-graphql' ),
				'args' => Relay::connectionArgs(),
				'resolve' => function( $source, $args, $context, ResolveInfo $info ) {
					return DataSource::resolve_post_types_connection( $source, $args, $context, $info );
				},
			];

		}

		return self::$post_types_connection;

	}

	/**
	 * This sets up a connection to posts (of a specified taxonomy).
	 * This establishes the Relay connection specs, setting up the edges/node/cursor structure.
	 *
	 * @param \WP_Taxonomy $taxonomy_object Taxonomy object for the taxonomy we want to make the
	 *                                      connection for
	 *
	 * @return array
	 * @throws \Exception
	 * @since 0.0.5
	 */
	public static function term_objects_connection( $taxonomy_object ) {

		if ( empty( $taxonomy_object ) || empty( $taxonomy_object->name ) ) {
			throw new \Exception( __( 'Tried instantiating a term_object_connection for an invalid taxonomy', 'wp-graphql' ) );
		}

		if ( null === self::$term_objects_connection ) {
			self::$term_objects_connection = [];
		}

		if ( empty( self::$term_objects_connection[ $taxonomy_object->name ] ) ) {

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

			self::$term_objects_connection[ $taxonomy_object->name ] = [
				'type' => $connection['connectionType'],
				'description' => sprintf( __( 'A collection of %s objects', 'wp-graphql' ), $taxonomy_object->graphql_plural_name ),
				'args' => array_merge( Relay::connectionArgs(), $args ),
				'resolve' => function( $source, $args, $context, ResolveInfo $info ) use ( $taxonomy_object ) {
					return DataSource::resolve_term_objects_connection( $taxonomy_object->name, $source, $args, $context, $info );
				},
			];
		}

		return self::$term_objects_connection[ $taxonomy_object->name ];

	}

	/**
	 * Method that sets up the relay connection for themes
	 *
	 * @return array
	 * @since  0.0.5
	 * @access public
	 */
	public static function themes_connection() {

		if ( null === self::$themes_connection ) {

			/**
			 * Setup the connectionDefinition
			 * @since 0.0.5
			 */
			$connection = Relay::connectionDefinitions( [
				'nodeType' => Types::theme(),
				'name' => 'themes',
			] );

			/**
			 * Add the connection to the post_objects_connection object
			 * @since 0.0.5
			 */
			self::$themes_connection = [
				'type' => $connection['connectionType'],
				'description' => __( 'A collection of theme objects', 'wp-graphql' ),
				'args' => Relay::connectionArgs(),
				'resolve' => function( $source, $args, $context, ResolveInfo $info ) {
					return DataSource::resolve_themes_connection( $source, $args, $context, $info );
				},
			];

		}

		return self::$themes_connection;

	}

	/**
	 * Method that sets up the relay connection for users
	 *
	 * @return mixed
	 * @since  0.0.5
	 * @access public
	 */
	public static function users_connection() {

		if ( null === self::$users_connection ) {
			$users_connection = Relay::connectionDefinitions( [
				'nodeType' => Types::user(),
			] );

			/**
			 * Add the "where" args to the commentConnection
			 * @since 0.0.5
			 */
			$args = [
				'where' => [
					'name' => 'where',
					'type' => Types::user_connection_query_args(),
				],
			];

			self::$users_connection = [
				'type' => $users_connection['connectionType'],
				'description' => __( 'A collection of user objects', 'wp-graphql' ),
				'args' => array_merge( Relay::connectionArgs(), $args ),
				'resolve' => function( $source, $args, $context, ResolveInfo $info ) {
					return DataSource::resolve_users_connection( $source, $args, $context, $info );
				},
			];
		}

		return self::$users_connection;

	}

}
