<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Type\Comment\Connection\CommentConnectionDefinition;
use WPGraphQL\TypeRegistry;

class RootQuery {
	public static function register_type() {
		register_graphql_type( 'RootQuery', new RootQueryType() );

		register_graphql_fields( 'RootQuery', [
			'comment' => [
				'type' => TypeRegistry::get_type( 'comment' ),
				'description' => __( 'Returns a Comment', 'wp-graphql' ),
				'args' => [
					'id' => [
						'type'        => [
							'non_null' => 'ID'
						],
					],
				],
				'resolve' => function( $source, array $args, AppContext $context, ResolveInfo $info ) {
					$id_components = Relay::fromGlobalId( $args['id'] );

					return DataSource::resolve_comment( $id_components['id'] );
				},
			],
			'comments' => CommentConnectionDefinition::connection(),
			'theme' => [
				'type' => 'Theme',
				'description' => __( 'A Theme object', 'wp-graphql' ),
				'args' => [
					'id' => [
						'type' => [
							'non_null' => 'ID',
						],
					],
				],
				'resolve' => function( $source, array $args, $context, ResolveInfo $info ) {
					$id_components = Relay::fromGlobalId( $args['id'] );

					return DataSource::resolve_theme( $id_components['id'] );
				},
			],
			'viewer' => [
				'type' => 'User',
				'description' => __( 'Returns the current user', 'wp-graphql' ),
				'resolve' => function( $source, array $args, AppContext $context, ResolveInfo $info ) {
					return ( false !== $context->viewer->ID ) ? DataSource::resolve_user( $context->viewer->ID ) : null;
				},
			],
		]);

	}
}