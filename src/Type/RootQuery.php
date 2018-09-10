<?php
namespace WPGraphQL\Type;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Type\Comment\Connection\CommentConnectionDefinition;
use WPGraphQL\Type\Menu\Connection\MenuConnectionDefinition;
use WPGraphQL\Type\MenuItem\Connection\MenuItemConnectionDefinition;
use WPGraphQL\Type\Plugin\Connection\PluginConnectionDefinition;
use WPGraphQL\TypeRegistry;
use WPGraphQL\Types;

class RootQuery {
	public static function register_type() {

		$node_definition = DataSource::get_node_definition();

		register_graphql_type( 'RootQuery', new RootQueryType() );

		register_graphql_fields( 'RootQuery', [
			'allSettings' => [
				'type' => 'Settings',
				'description' => __( 'Entry point to get all settings for the site', 'wp-graphql' ),
				'resolve' => function() {
					return true;
				}
			],
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
			'node' => $node_definition['nodeField'],
			'menu' => [
				'type' => 'Menu',
				'description' => __( 'A WordPress navigation menu', 'wp-graphql' ),
				'args' => [
					'id' => [
						'type' => [
							'non_null' => 'ID',
						],
					],
				],
				'resolve' => function( $source, array $args, AppContext $context, ResolveInfo $info ) {
					$id_components = Relay::fromGlobalId( $args['id'] );

					return DataSource::resolve_term_object( $id_components['id'], 'nav_menu' );
				},
			],
			'menus' => MenuConnectionDefinition::connection(),
			'menuItem' => [
				'type' => 'MenuItem',
				'description' => __( 'A WordPress navigation menu item', 'wp-graphql' ),
				'args' => [
					'id' => [
						'type' => [
							'non_null' => 'ID',
						],
					],
				],
				'resolve' => function( $source, array $args, AppContext $context, ResolveInfo $info ) {
					$id_components = Relay::fromGlobalId( $args['id'] );

					return DataSource::resolve_post_object( $id_components['id'], 'nav_menu_item' );
				}
			],
			'menuItems' => MenuItemConnectionDefinition::connection(),
			'plugin' => [
				'type' => 'Plugin',
				'description' => __( 'A WordPress plugin', 'wp-graphql' ),
				'args' => [
					'id' => [
						'type' => [
							'non_null' => 'ID',
						],
					],
				],
				'resolve' => function( $source, array $args, AppContext $context, ResolveInfo $info ) {
					$id_components = Relay::fromGlobalId( $args['id'] );

					return DataSource::resolve_plugin( $id_components['id'] );
				},
			],
			'plugins' => PluginConnectionDefinition::connection(),
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