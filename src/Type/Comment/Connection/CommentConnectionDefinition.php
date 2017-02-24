<?php
namespace WPGraphQL\Type\Comment\Connection;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

/**
 * Class CommentConnectionDefinition
 * @package WPGraphQL\Type\Comment\Connection
 * @since 0.0.5
 */
class CommentConnectionDefinition {

	/**
	 * @var array connection
	 * @since 0.0.5
	 */
	private static $connection;

	/**
	 * Holds the input $args for the Connection
	 * @var $args InputObjectType
	 */
	private static $args;

	/**
	 * connection
	 * This sets up a connection of comments
	 * @return mixed
	 * @since 0.0.5
	 */
	public static function connection() {

		if ( null === self::$connection ) {
			$connection = Relay::connectionDefinitions( [
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
					'type' => self::args(),
				],
			];

			self::$connection = [
				'type' => $connection['connectionType'],
				'description' => __( 'A collection of comment objects', 'wp-graphql' ),
				'args' => array_merge( Relay::connectionArgs(), $args ),
				'resolve' => function( $source, $args, $context, ResolveInfo $info ) {
					return DataSource::resolve_comments_connection( $source, $args, $context, $info );
				},
			];

		}

		return self::$connection;
	}


	/**
	 * args
	 * @return CommentConnectionArgs
	 * @since 0.0.5
	 */
	private static function args() {
		return self::$args ? : ( self::$args = new CommentConnectionArgs() );
	}

}
