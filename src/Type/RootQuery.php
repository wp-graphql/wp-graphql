<?php
namespace WPGraphQL\Type;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Type\MenuItem\Connection\MenuItemConnectionDefinition;
use WPGraphQL\Type\Plugin\Connection\PluginConnectionDefinition;
use WPGraphQL\Type\Setting\SettingQuery;
use WPGraphQL\Type\Theme\Connection\ThemeConnectionDefinition;
use WPGraphQL\Type\UserRoles\Connection\UserRoleConnectionDefinition;
use WPGraphQL\TypeRegistry;

class RootQuery {
	public static function register_type() {

		$allowed_setting_types = DataSource::get_allowed_settings_by_group();
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
			'themes' => ThemeConnectionDefinition::connection(),
			'user' => [
				'type'        => 'User',
				'description' => __( 'Returns a user', 'wp-graphql' ),
				'args'        => [
					'id' => [
						'type' => [
							'non_null' => 'ID',
						],
					],
				],
				'resolve'     => function( $source, array $args, AppContext $context, ResolveInfo $info ) {
					$id_components = Relay::fromGlobalId( $args['id'] );

					return DataSource::resolve_user( $id_components['id'] );
				},
			],
			'userRole' => [
				'type'        => 'UserRole',
				'description' => __( 'Returns a user role', 'wp-graphql' ),
				'args'        => [
					'id' => [
						'type' => [
							'non_null' => 'ID',
						]
					],
				],
				'resolve'     => function ( $source, array $args, AppContext $context, ResolveInfo $info ) {

					if ( current_user_can( 'list_users' ) ) {
						$id_components = Relay::fromGlobalId( $args['id'] );
						return DataSource::resolve_user_role( $id_components['id'] );
					} else {
						throw new UserError( __( 'The current user does not have the proper privileges to query this data', 'wp-graphql' ) );
					}

				}
			],
			'userRoles' => UserRoleConnectionDefinition::connection(),
			'viewer' => [
				'type' => 'User',
				'description' => __( 'Returns the current user', 'wp-graphql' ),
				'resolve' => function( $source, array $args, AppContext $context, ResolveInfo $info ) {
					return ( false !== $context->viewer->ID ) ? DataSource::resolve_user( $context->viewer->ID ) : null;
				},
			],
		]);

		/**
		 * Create the root query fields for any setting type in
		 * the $allowed_setting_types array.
		 */
		if ( ! empty( $allowed_setting_types ) && is_array( $allowed_setting_types ) ) {
			foreach ( $allowed_setting_types as $group => $setting_type ) {
				$setting_type = str_replace('_', '', strtolower( $group ) );
				register_graphql_field( 'RootQuery', $setting_type . 'Settings', SettingQuery::root_query( $group, $setting_type ) );
			}
		}

	}
}