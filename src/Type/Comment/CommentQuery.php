<?php
namespace WPGraphQL\Type\Comment;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

class CommentQuery {

	/**
	 * Holds the root_query field definition
	 * @var array $root_query
	 */
	private static $root_query;


	/**
	 * root_query
	 * @return array
	 */
	public static function root_query() {

		if ( null === self::$root_query ) {
			self::$root_query = [
				'type' => Types::comment(),
				'description' => __( 'Returns a Comment', 'wp-graphql' ),
				'args' => [
					'id' => Types::non_null( Types::id() ),
				],
				'resolve' => function( $source, array $args, AppContext $context, ResolveInfo $info ) {
					$id_components = Relay::fromGlobalId( $args['id'] );

					return DataSource::resolve_comment( $id_components['id'] );
				},
			];
		}

		return ! empty( self::$root_query ) ? self::$root_query : null;

	}

}
