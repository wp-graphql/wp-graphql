<?php

namespace WPGraphQL\Type;

use GraphQL\Error\UserError;
use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;

$node_definition = DataSource::get_node_definition();

register_graphql_object_type( 'RootQuery', [
	'description' => __( 'The root entry point into the Graph', 'wp-graphql' ),
	'fields'      => [
		'allSettings' => [
			'type'        => 'Settings',
			'description' => __( 'Entry point to get all settings for the site', 'wp-graphql' ),
			'resolve'     => function () {
				return true;
			}
		],
		'comment'     => [
			'type'        => 'Comment',
			'description' => __( 'Returns a Comment', 'wp-graphql' ),
			'args'        => [
				'id' => [
					'type' => [
						'non_null' => 'ID'
					],
				],
			],
			'resolve'     => function ( $source, array $args, $context, $info ) {
				$id_components = Relay::fromGlobalId( $args['id'] );

				return DataSource::resolve_comment( $id_components['id'] );
			},
		],
		'node'        => $node_definition['nodeField'],
		'menu'        => [
			'type'        => 'Menu',
			'description' => __( 'A WordPress navigation menu', 'wp-graphql' ),
			'args'        => [
				'id' => [
					'type' => [
						'non_null' => 'ID',
					],
				],
			],
			'resolve'     => function ( $source, array $args, $context, $info ) {
				$id_components = Relay::fromGlobalId( $args['id'] );

				return DataSource::resolve_term_object( $id_components['id'], 'nav_menu' );
			},
		],
		'menuItem'    => [
			'type'        => 'MenuItem',
			'description' => __( 'A WordPress navigation menu item', 'wp-graphql' ),
			'args'        => [
				'id' => [
					'type' => [
						'non_null' => 'ID',
					],
				],
			],
			'resolve'     => function ( $source, array $args, $context, $info ) {
				$id_components = Relay::fromGlobalId( $args['id'] );

				return DataSource::resolve_post_object( $id_components['id'], 'nav_menu_item' );
			}
		],
		'plugin'      => [
			'type'        => 'Plugin',
			'description' => __( 'A WordPress plugin', 'wp-graphql' ),
			'args'        => [
				'id' => [
					'type' => [
						'non_null' => 'ID',
					],
				],
			],
			'resolve'     => function ( $source, array $args, $context, $info ) {
				$id_components = Relay::fromGlobalId( $args['id'] );

				return DataSource::resolve_plugin( $id_components['id'] );
			},
		],
		'theme'       => [
			'type'        => 'Theme',
			'description' => __( 'A Theme object', 'wp-graphql' ),
			'args'        => [
				'id' => [
					'type' => [
						'non_null' => 'ID',
					],
				],
			],
			'resolve'     => function ( $source, array $args, $context, $info ) {
				$id_components = Relay::fromGlobalId( $args['id'] );

				return DataSource::resolve_theme( $id_components['id'] );
			},
		],
		'user'        => [
			'type'        => 'User',
			'description' => __( 'Returns a user', 'wp-graphql' ),
			'args'        => [
				'id' => [
					'type' => [
						'non_null' => 'ID',
					],
				],
			],
			'resolve'     => function ( $source, array $args, $context, $info ) {
				$id_components = Relay::fromGlobalId( $args['id'] );

				return DataSource::resolve_user( $id_components['id'] );
			},
		],
		'userRole'    => [
			'type'        => 'UserRole',
			'description' => __( 'Returns a user role', 'wp-graphql' ),
			'args'        => [
				'id' => [
					'type' => [
						'non_null' => 'ID',
					]
				],
			],
			'resolve'     => function ( $source, array $args, $context, $info ) {

				if ( current_user_can( 'list_users' ) ) {
					$id_components = Relay::fromGlobalId( $args['id'] );

					return DataSource::resolve_user_role( $id_components['id'] );
				} else {
					throw new UserError( __( 'The current user does not have the proper privileges to query this data', 'wp-graphql' ) );
				}

			}
		],
		'viewer'      => [
			'type'        => 'User',
			'description' => __( 'Returns the current user', 'wp-graphql' ),
			'resolve'     => function ( $source, array $args, $context, $info ) {
				return ( false !== $context->viewer->ID ) ? DataSource::resolve_user( $context->viewer->ID ) : null;
			},
		],
	],
] );

$allowed_post_types = \WPGraphQL::$allowed_post_types;
if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {
	foreach ( $allowed_post_types as $post_type ) {
		$post_type_object = get_post_type_object( $post_type );

		register_graphql_field( 'RootQuery', $post_type_object->graphql_single_name, [
			'type'        => $post_type_object->graphql_single_name,
			'description' => sprintf( __( 'A % object', 'wp-graphql' ), $post_type_object->graphql_single_name ),
			'args'        => [
				'id' => [
					'type' => [
						'non_null' => 'ID',
					],
				],
			],
			'resolve'     => function ( $source, array $args, $context, $info ) use ( $post_type_object ) {
				$id_components = Relay::fromGlobalId( $args['id'] );

				return DataSource::resolve_post_object( $id_components['id'], $post_type_object->name );
			},

		] );

		$post_by_args = [
			'id'                                          => [
				'type'        => 'ID',
				'description' => sprintf( __( 'Get the object by its global ID', 'wp-graphql' ), $post_type_object->graphql_single_name ),
			],
			$post_type_object->graphql_single_name . 'Id' => [
				'type'        => 'Int',
				'description' => sprintf( __( 'Get the %s by its database ID', 'wp-graphql' ), $post_type_object->graphql_single_name ),
			],
			'uri'                                         => [
				'type'        => 'String',
				'description' => sprintf( __( 'Get the %s by its uri', 'wp-graphql' ), $post_type_object->graphql_single_name ),
			]
		];

		if ( false === $post_type_object->hierarchical ) {
			$post_by_args['slug'] = [
				'type'        => 'String',
				'description' => sprintf( __( 'Get the %s by its slug (only available for non-hierarchical types)', 'wp-graphql' ), $post_type_object->graphql_single_name ),
			];
		}

		register_graphql_field( 'RootQuery', $post_type_object->graphql_single_name . 'By', [
			'type'        => $post_type_object->graphql_single_name,
			'description' => sprintf( __( 'A %s object', 'wp-graphql' ), $post_type_object->graphql_single_name ),
			'args'        => $post_by_args,
			'resolve'     => function ( $source, array $args, $context, $info ) use ( $post_type_object ) {

				$post_object = null;

				if ( ! empty( $args['id'] ) ) {
					$id_components = Relay::fromGlobalId( $args['id'] );
					if ( empty( $id_components['id'] ) || empty( $id_components['type'] ) ) {
						throw new UserError( __( 'The "id" is invalid', 'wp-graphql' ) );
					}
					$post_object = DataSource::resolve_post_object( absint( $id_components['id'] ), $post_type_object->name );
				} elseif ( ! empty( $args[ $post_type_object->graphql_single_name . 'Id' ] ) ) {
					$id          = $args[ $post_type_object->graphql_single_name . 'Id' ];
					$post_object = DataSource::resolve_post_object( $id, $post_type_object->name );
				} elseif ( ! empty( $args['uri'] ) ) {
					$uri         = esc_html( $args['uri'] );
					$post_object = DataSource::get_post_object_by_uri( $uri, 'OBJECT', $post_type_object->name );
				} elseif ( ! empty( $args['slug'] ) ) {
					$slug        = esc_html( $args['slug'] );
					$post_object = DataSource::get_post_object_by_uri( $slug, 'OBJECT', $post_type_object->name );
				}

				if ( empty( $post_object ) || is_wp_error( $post_object ) ) {
					throw new UserError( __( 'No resource could be found', 'wp-graphql' ) );
				}

				if ( ! $post_object instanceof \WP_Post ) {
					throw new UserError( __( 'The queried resource is not valid', 'wp-graphql' ) );
				}

				if ( $post_type_object->name !== $post_object->post_type ) {
					throw new UserError( __( 'The queried resource is not the correct type', 'wp-graphql' ) );
				}

				return $post_object;

			},
		] );

	}
}

$allowed_taxonomies = \WPGraphQL::$allowed_taxonomies;
if ( ! empty( $allowed_taxonomies ) && is_array( $allowed_taxonomies ) ) {
	foreach ( $allowed_taxonomies as $taxonomy ) {
		$taxonomy_object = get_taxonomy( $taxonomy );
		register_graphql_field( 'RootQuery', $taxonomy_object->graphql_single_name, [
			'type'        => $taxonomy_object->graphql_single_name,
			'description' => sprintf( __( 'A % object', 'wp-graphql' ), $taxonomy_object->graphql_single_name ),
			'args'        => [
				'id' => [
					'type' => [
						'non_null' => 'ID',
					],
				],
			],
			'resolve'     => function ( $source, array $args, $context, $info ) use ( $taxonomy_object ) {
				$id_components = Relay::fromGlobalId( $args['id'] );

				return DataSource::resolve_term_object( $id_components['id'], $taxonomy_object->name );
			},
		] );

	}
}