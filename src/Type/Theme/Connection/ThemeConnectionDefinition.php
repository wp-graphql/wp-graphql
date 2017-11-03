<?php
namespace WPGraphQL\Type\Theme\Connection;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

/**
 * Class ThemeConnectionDefinition
 * @package WPGraphQL\Type\Comment\Connection
 * @since 0.0.5
 */
class ThemeConnectionDefinition {

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
	 * @return mixed
	 * @since 0.0.5
	 */
	public static function connection() {

		if ( null === self::$connection ) :
			/**
			 * Setup the connectionDefinition
			 * @since 0.0.5
			 */
			$connection = Relay::connectionDefinitions( [
				'nodeType' => Types::theme(),
				'name' => 'Themes',
				'connectionFields' => function() {
					return [
						'nodes' => [
							'type' => Types::list_of( Types::theme() ),
							'description' => __( 'The nodes of the connection, without the edges', 'wp-graphql' ),
							'resolve' => function( $source, $args, $context, $info ) {
								return ! empty( $source['nodes'] ) ? $source['nodes'] : [];
							},
						],
					];
				},
			] );

			/**
			 * Add the connection to the themes_connection object
			 * @since 0.0.5
			 */
			self::$connection = [
				'type' => $connection['connectionType'],
				'description' => __( 'A collection of theme objects', 'wp-graphql' ),
				'args' => Relay::connectionArgs(),
				'resolve' => function( $source, $args, AppContext $context, ResolveInfo $info ) {
					return DataSource::resolve_themes_connection( $source, $args, $context, $info );
				},
			];
		endif;
		return self::$connection;
	}

}
