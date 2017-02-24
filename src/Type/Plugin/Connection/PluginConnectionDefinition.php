<?php
namespace WPGraphQL\Type\Plugin\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

/**
 * Class CommentConnectionDefinition
 * @package WPGraphQL\Type\Comment\Connection
 * @since 0.0.5
 */
class PluginConnectionDefinition {

	/**
	 * @var array connection
	 * @since 0.0.5
	 */
	private static $connection;

	/**
	 * connection
	 * This sets up a connection of plugins
	 * @return mixed
	 * @since 0.0.5
	 */
	public static function connection() {

		if ( null === self::$connection ) {

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
			self::$connection = [
				'type' => $connection['connectionType'],
				'description' => __( 'A collection of plugins', 'wp-graphql' ),
				'args' => Relay::connectionArgs(),
				'resolve' => function( $source, $args, $context, ResolveInfo $info ) {
					return DataSource::resolve_plugins_connection( $source, $args, $context, $info );
				},
			];

		}

		return self::$connection;

	}

}
