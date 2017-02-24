<?php
namespace WPGraphQL\Type\PostObject;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

/**
 * Class PostObjectQuery
 * @package WPGraphQL\Type\PostObject
 * @Since 0.0.5
 */
class PostObjectQuery {

	/**
	 * Holds the root_query field definition
	 * @var array $root_query
	 * @since 0.0.5
	 */
	private static $root_query;

	/**
	 * Method that returns the root query field definition for the post object type
	 *
	 * @param object $post_type_object
	 * @return array
	 * @since 0.0.5
	 */
	public static function root_query( $post_type_object ) {

		if ( null === self::$root_query ) {

			self::$root_query = [
				'type' => Types::post_object( $post_type_object->name ),
				'description' => sprintf( __( 'A % object', 'wp-graphql' ), $post_type_object->graphql_single_name ),
				'args' => [
					'id' => Types::non_null( Types::id() ),
				],
				'resolve' => function( $source, array $args, $context, ResolveInfo $info ) use ( $post_type_object ) {
					$id_components = Relay::fromGlobalId( $args['id'] );

					return DataSource::resolve_post_object( $id_components['id'], $post_type_object->name );
				},
			];

		}

		return self::$root_query;
	}

}
