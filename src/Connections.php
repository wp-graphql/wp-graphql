<?php
namespace WPGraphQL;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;

class Connections {

	/**
	 * @var array wp_posts_connection
	 * @since 0.0.5
	 */
	protected static $wp_posts_connection;

	/**
	 * @var array wp_terms_connection
	 * @since 0.0.5
	 */
	protected static $wp_terms_connection;

	/**
	 * @var array wp_users_connection
	 * @since 0.0.5
	 */
	protected static $wp_users_connection;

	/**
	 * wp_posts_connection
	 *
	 * This sets up a connection to posts (of a specified post_type).
	 * This establishes the Relay connection specs, setting up the edges/node/cursor structure.
	 *
	 * @param $post_type_object
	 * @param bool $name
	 * @return mixed
	 */
	public static function wp_posts_connection( $post_type_object ) {

		if ( null === self::$wp_posts_connection->{ $post_type_object->name } ) {

			$connection = Relay::connectionDefinitions( [
				'nodeType' => Types::wp_post( $post_type_object->name ),
				'name'     => $post_type_object->graphql_plural_name,
			] );

			self::$wp_posts_connection->{ $post_type_object->name } = [
				'type'        => $connection['connectionType'],
				'description' => sprintf( __( 'A collection of % objects', 'wp-graphql' ), $post_type_object->graphql_plural_name ),
				'args'        => Relay::connectionArgs(),
				'resolve'     => function( $source, $args, $context, ResolveInfo $info ) use ( $post_type_object ) {
					return DataSource::resolve_wp_posts( $post_type_object->name, $source, $args, $context, $info );
				},
			];
		}

		return self::$wp_posts_connection->{ $post_type_object->name };
	}

	/**
	 * wp_terms_connection
	 *
	 * This sets up a connection to posts (of a specified taxonomy).
	 * This establishes the Relay connection specs, setting up the edges/node/cursor structure.
	 *
	 * @param $taxonomy_object
	 * @param bool $name
	 * @return mixed
	 * @since 0.0.5
	 */
	public static function wp_terms_connection( $taxonomy_object ) {

		if ( null === self::$wp_terms_connection->{ $taxonomy_object->name } ) {

			$connection = Relay::connectionDefinitions( [
				'nodeType' => Types::wp_term( $taxonomy_object->name ),
				'name'     => $taxonomy_object->graphql_plural_name,
			] );

			self::$wp_terms_connection->{ $taxonomy_object->name } = [
				'type'        => $connection['connectionType'],
				'description' => sprintf( __( 'A collection of % objects', 'wp-graphql' ), $taxonomy_object->graphql_plural_name ),
				'args'        => Relay::connectionArgs(),
				'resolve'     => function( $source, $args, $context, ResolveInfo $info ) use ( $taxonomy_object ) {
					return DataSource::resolve_wp_terms( $taxonomy_object->name, $source, $args, $context, $info );
				},
			];
		}

		return self::$wp_terms_connection->{ $taxonomy_object->name };

	}

	/**
	 * @return array
	 */
	public static function wp_users_connection() {

		if ( null === self::$wp_users_connection ) {
			$wp_users_connection = Relay::connectionDefinitions( [
				'nodeType' => Types::wp_user(),
			] );

			self::$wp_users_connection = $wp_users_connection;
		}

		return self::$wp_users_connection;

	}

}