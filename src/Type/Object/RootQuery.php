<?php

namespace WPGraphQL\Type\Object;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\Term;

class RootQuery {

	/**
	 * Register the RootQuery type
	 */
	public static function register_type() {
		register_graphql_object_type(
			'RootQuery',
			[
				'description' => __( 'The root entry point into the Graph', 'wp-graphql' ),
				'fields'      => [
					'allSettings' => [
						'type'        => 'Settings',
						'description' => __( 'Entry point to get all settings for the site', 'wp-graphql' ),
						'resolve'     => function() {
							return true;
						},
					],
					'comment'     => [
						'type'        => 'Comment',
						'description' => __( 'Returns a Comment', 'wp-graphql' ),
						'args'        => [
							'id' => [
								'type' => [
									'non_null' => 'ID',
								],
							],
						],
						'resolve'     => function( $source, array $args, AppContext $context, $info ) {
							$id_components = Relay::fromGlobalId( $args['id'] );

							return DataSource::resolve_comment( $id_components['id'], $context );
						},
					],
					'contentNode' => [
						'type'        => 'ContentNode',
						'description' => __( 'A node used to manage content', 'wp-graphql' ),
						'args'        => [
							'id'          => [
								'type' => [
									'non_null'    => 'ID',
									'description' => __( 'Unique identifier for the content node', 'wp-graphql' ),
								],
							],
							'idType'      => [
								'type'        => 'ContentNodeIdTypeEnum',
								'description' => __( 'Type of unique identifier to fetch a content node by. Default is Global ID', 'wp-graphql' ),
							],
							'contentType' => [
								'type'        => 'PostTypeEnum',
								'description' => __( 'The content type the node is used for. Required when idType is set to "name" or "slug"', 'wp-graphql' ),
							],
						],
						'resolve'     => function( $root, $args, AppContext $context, ResolveInfo $info ) {

							$idType  = isset( $args['idType'] ) ? $args['idType'] : 'global_id';
							$post_id = null;
							switch ( $idType ) {
								case 'uri':
									$post_object = DataSource::resolve_resource_by_uri( $args['id'], $context, $info );
									$post_id     = isset( $post_object->ID ) ? absint( $post_object->ID ) : null;
									break;
								case 'database_id':
									$post_id = absint( $args['id'] );
									break;
								case 'global_id':
								default:
									$id_components = Relay::fromGlobalId( $args['id'] );
									if ( ! isset( $id_components['id'] ) || ! absint( $id_components['id'] ) ) {
										throw new UserError( __( 'The ID input is invalid. Make sure you set the proper idType for your input.', 'wp-graphql' ) );
									}
									$post_id = absint( $id_components['id'] );
									break;
							}

							return ! empty( $post_id ) ? DataSource::resolve_post_object( $post_id, $context ) : null;

						},
					],
					'node'        => [
						'type'        => 'Node',
						'args'        => [
							'id' => [
								'type'        => 'ID',
								'description' => __( 'The unique identifier of the node', 'wp-graphql' ),
							],
						],
						'description' => __( 'Fetches an object given its ID', 'wp-graphql' ),
						'resolve'     => function( $root, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $args['id'] ) ? DataSource::resolve_node( $args['id'], $context, $info ) : null;
						},
					],
					'nodeByUri'   => [
						'type'    => 'UniformResourceIdentifiable',
						'args'    => [
							'uri' => [
								'type'        => [ 'non_null' => 'String' ],
								'description' => __( 'Unique Resource Identifier in the form of a path or permalink for a node. Ex: "/hello-world"', 'wp-graphql' ),
							],
						],
						'resolve' => function( $root, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $args['uri'] ) ? DataSource::resolve_resource_by_uri( $args['uri'], $context, $info ) : null;
						},
					],
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
						'resolve'     => function( $source, array $args, $context, $info ) {
							$id_components = Relay::fromGlobalId( $args['id'] );

							return DataSource::resolve_term_object( $id_components['id'], $context );
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
						'resolve'     => function( $source, array $args, AppContext $context, ResolveInfo $info ) {
							$id_components = Relay::fromGlobalId( $args['id'] );
							$id            = absint( $id_components['id'] );

							return DataSource::resolve_menu_item( $id, $context );
						},
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
						'resolve'     => function( $source, array $args, $context, $info ) {
							$id_components = Relay::fromGlobalId( $args['id'] );

							return DataSource::resolve_plugin( $id_components['id'] );
						},
					],
					'termNode'    => [
						'type'        => 'TermNode',
						'description' => __( 'A node in a taxonomy used to group and relate content nodes', 'wp-graphql' ),
						'args'        => [
							'id'       => [
								'type' => [
									'non_null'    => 'ID',
									'description' => __( 'Unique identifier for the term node', 'wp-graphql' ),
								],
							],
							'idType'   => [
								'type'        => 'TermNodeIdTypeEnum',
								'description' => __( 'Type of unique identifier to fetch a term node by. Default is Global ID', 'wp-graphql' ),
							],
							'taxonomy' => [
								'type'        => 'TaxonomyEnum',
								'description' => __( 'The taxonomy of the tern node. Required when idType is set to "name" or "slug"', 'wp-graphql' ),
							],
						],
						'resolve'     => function( $root, $args, AppContext $context, ResolveInfo $info ) {

							$idType  = isset( $args['idType'] ) ? $args['idType'] : 'global_id';
							$term_id = null;

							switch ( $idType ) {
								case 'slug':
								case 'name':
								case 'database_id':
									$taxonomy = isset( $args['taxonomy'] ) ? $args['taxonomy'] : null;
									if ( empty( $taxonomy ) && in_array(
										$idType,
										[
											'name',
											'slug',
										],
										true
									) ) {
										throw new UserError( __( 'When fetching a Term Node by "slug" or "name", the "taxonomy" also needs to be set as an input.', 'wp-graphql' ) );
									}
									if ( 'database_id' === $idType ) {
										$term = get_term( absint( $args['id'] ) );
									} else {
										$term = get_term_by( $idType, $args['id'], $taxonomy );
									}
									$term_id = isset( $term->term_id ) ? absint( $term->term_id ) : null;

									break;
								case 'uri':
									$term = DataSource::resolve_resource_by_uri( $args['id'], $context, $info );
									if ( $term instanceof Term ) {
										$term_id = $term->term_id;
									}
									break;
								case 'global_id':
								default:
									$id_components = Relay::fromGlobalId( $args['id'] );
									if ( ! isset( $id_components['id'] ) || ! absint( $id_components['id'] ) ) {
										throw new UserError( __( 'The ID input is invalid', 'wp-graphql' ) );
									}
									$term_id = absint( $id_components['id'] );
									break;

							}

							return ! empty( $term_id ) ? DataSource::resolve_term_object( $term_id, $context ) : null;

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
						'resolve'     => function( $source, array $args, $context, $info ) {
							$id_components = Relay::fromGlobalId( $args['id'] );

							return DataSource::resolve_theme( $id_components['id'] );
						},
					],
					'user'        => [
						'type'        => 'User',
						'description' => __( 'Returns a user', 'wp-graphql' ),
						'args'        => [
							'id'     => [
								'type' => [
									'non_null' => 'ID',
								],
							],
							'idType' => [
								'type' => 'UserNodeIdTypeEnum',
							],
						],
						'resolve'     => function( $source, array $args, $context, $info ) {

							$idType = isset( $args['idType'] ) ? $args['idType'] : 'id';

							switch ( $idType ) {
								case 'database_id':
									$id = absint( $args['id'] );
									break;
								case 'uri':
									$user = DataSource::resolve_resource_by_uri( $args['id'], $context, $info );
									$id   = null;
									if ( $user instanceof \WPGraphQL\Model\User ) {
										$id = $user->userId;
									}
									break;
								case 'login':
									$current_user = wp_get_current_user();
									if ( $current_user->user_login !== $args['id'] ) {
										if ( ! current_user_can( 'list_users' ) ) {
											throw new UserError( __( 'You do not have permission to request a User by Username', 'wp-graphql' ) );
										}
									}

									$user = get_user_by( 'login', $args['id'] );
									$id   = isset( $user->ID ) ? $user->ID : null;
									break;
								case 'email':
									$current_user = wp_get_current_user();
									if ( $current_user->user_email !== $args['id'] ) {
										if ( ! current_user_can( 'list_users' ) ) {
											throw new UserError( __( 'You do not have permission to request a User by Email', 'wp-graphql' ) );
										}
									}

									$user = get_user_by( 'email', $args['id'] );
									$id   = isset( $user->ID ) ? $user->ID : null;
									break;
								case 'slug':
									$user = get_user_by( 'slug', $args['id'] );
									$id   = isset( $user->ID ) ? $user->ID : null;
									break;
								case 'id':
								default:
									$id_components = Relay::fromGlobalId( $args['id'] );
									$id            = absint( $id_components['id'] );
									break;
							}

							return ! empty( $id ) ? DataSource::resolve_user( $id, $context ) : null;
						},
					],
					'userRole'    => [
						'type'        => 'UserRole',
						'description' => __( 'Returns a user role', 'wp-graphql' ),
						'args'        => [
							'id' => [
								'type' => [
									'non_null' => 'ID',
								],
							],
						],
						'resolve'     => function( $source, array $args, $context, $info ) {

							$id_components = Relay::fromGlobalId( $args['id'] );

							return DataSource::resolve_user_role( $id_components['id'] );

						},
					],
					'viewer'      => [
						'type'        => 'User',
						'description' => __( 'Returns the current user', 'wp-graphql' ),
						'resolve'     => function( $source, array $args, $context, $info ) {
							if ( ! isset( $context->viewer->ID ) || empty( $context->viewer->ID ) ) {
								throw new \Exception( __( 'You must be logged in to access viewer fields', 'wp-graphql' ) );
							}

							return ( false !== $context->viewer->ID ) ? DataSource::resolve_user( $context->viewer->ID, $context ) : null;
						},
					],
				],
			]
		);
	}

	/**
	 * Register RootQuery fields for Post Objects of supported post types
	 *
	 * @access public
	 */
	public static function register_post_object_fields() {

		$allowed_post_types = \WPGraphQL::get_allowed_post_types();
		if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {
			foreach ( $allowed_post_types as $post_type ) {
				$post_type_object = get_post_type_object( $post_type );

				register_graphql_field(
					'RootQuery',
					$post_type_object->graphql_single_name,
					[
						'type'        => $post_type_object->graphql_single_name,
						'description' => sprintf( __( 'A % object', 'wp-graphql' ), $post_type_object->graphql_single_name ),
						'args'        => [
							'id'     => [
								'type' => [
									'non_null' => 'ID',
								],
							],
							'idType' => [
								'type' => $post_type_object->graphql_single_name . 'IdType',
							],
						],
						'resolve'     => function( $source, array $args, AppContext $context, ResolveInfo $info ) use ( $post_type_object ) {

							$idType  = isset( $args['idType'] ) ? $args['idType'] : 'global_id';
							$post_id = null;
							switch ( $idType ) {
								case 'uri':
								case 'slug':
									$slug        = esc_html( $args['id'] );
									$post_object = DataSource::get_post_object_by_uri( $slug, 'OBJECT', $post_type_object->name );
									$post_id     = isset( $post_object->ID ) ? absint( $post_object->ID ) : null;
									break;
								case 'database_id':
									$post_id = absint( $args['id'] );
									break;
								case 'source_url':
									$url     = $args['id'];
									$post_id = absint( attachment_url_to_postid( $url ) );
									break;
								case 'global_id':
								default:
									$id_components = Relay::fromGlobalId( $args['id'] );
									if ( ! isset( $id_components['id'] ) || ! absint( $id_components['id'] ) ) {
										throw new UserError( __( 'The ID input is invalid. Make sure you set the proper idType for your input.', 'wp-graphql' ) );
									}
									$post_id = absint( $id_components['id'] );
									break;
							}

							return ! empty( $post_id ) ? DataSource::resolve_post_object( $post_id, $context ) : null;
						},
					]
				);
				$post_by_args = [
					'id'  => [
						'type'        => 'ID',
						'description' => sprintf( __( 'Get the object by its global ID', 'wp-graphql' ), $post_type_object->graphql_single_name ),
					],
					$post_type_object->graphql_single_name . 'Id' => [
						'type'        => 'Int',
						'description' => sprintf( __( 'Get the %s by its database ID', 'wp-graphql' ), $post_type_object->graphql_single_name ),
					],
					'uri' => [
						'type'        => 'String',
						'description' => sprintf( __( 'Get the %s by its uri', 'wp-graphql' ), $post_type_object->graphql_single_name ),
					],
				];
				if ( false === $post_type_object->hierarchical ) {
					$post_by_args['slug'] = [
						'type'        => 'String',
						'description' => sprintf( __( 'Get the %s by its slug (only available for non-hierarchical types)', 'wp-graphql' ), $post_type_object->graphql_single_name ),
					];
				}

				/**
				 * @deprecated Deprecated in favor of single node entry points
				 */
				register_graphql_field(
					'RootQuery',
					$post_type_object->graphql_single_name . 'By',
					[
						'type'              => $post_type_object->graphql_single_name,
						'isDeprecated'      => true,
						'deprecationReason' => __( 'Deprecated in favor of using the single entry point for this type with ID and IDType fields. For example, instead of postBy( id: "" ), use post(id: "" idType: "")', 'wp-graphql' ),
						'description'       => sprintf( __( 'A %s object', 'wp-graphql' ), $post_type_object->graphql_single_name ),
						'args'              => $post_by_args,
						'resolve'           => function( $source, array $args, $context, $info ) use ( $post_type_object ) {
							$post_object = null;
							$post_id     = 0;
							if ( ! empty( $args['id'] ) ) {
								$id_components = Relay::fromGlobalId( $args['id'] );
								if ( empty( $id_components['id'] ) || empty( $id_components['type'] ) ) {
									throw new UserError( __( 'The "id" is invalid', 'wp-graphql' ) );
								}
								$post_id = absint( $id_components['id'] );
							} elseif ( ! empty( $args[ lcfirst( $post_type_object->graphql_single_name . 'Id' ) ] ) ) {
								$id      = $args[ lcfirst( $post_type_object->graphql_single_name . 'Id' ) ];
								$post_id = absint( $id );
							} elseif ( ! empty( $args['uri'] ) ) {
								$uri         = esc_html( $args['uri'] );
								$post_object = DataSource::get_post_object_by_uri( $uri, 'OBJECT', $post_type_object->name );
								$post_id     = isset( $post_object->ID ) ? absint( $post_object->ID ) : null;
							} elseif ( ! empty( $args['slug'] ) ) {
								$slug        = esc_html( $args['slug'] );
								$post_object = DataSource::get_post_object_by_uri( $slug, 'OBJECT', $post_type_object->name );
								$post_id     = isset( $post_object->ID ) ? absint( $post_object->ID ) : null;
							}
							$post = DataSource::resolve_post_object( $post_id, $context );
							if ( ! get_post( $post_id ) || get_post( $post_id )->post_type !== $post_type_object->name ) {
								return null;
							}

							return $post;
						},
					]
				);
			}
		}
	}

	/**
	 * Register RootQuery fields for Term Objects of supported taxonomies
	 *
	 * @access public
	 */
	public static function register_term_object_fields() {

		$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies();
		if ( ! empty( $allowed_taxonomies ) && is_array( $allowed_taxonomies ) ) {
			foreach ( $allowed_taxonomies as $taxonomy ) {
				$taxonomy_object = get_taxonomy( $taxonomy );

				register_graphql_field(
					'RootQuery',
					$taxonomy_object->graphql_single_name,
					[
						'type'        => $taxonomy_object->graphql_single_name,
						'description' => sprintf( __( 'A % object', 'wp-graphql' ), $taxonomy_object->graphql_single_name ),
						'args'        => [
							'id'     => [
								'type' => [
									'non_null' => 'ID',
								],
							],
							'idType' => [
								'type' => $taxonomy_object->graphql_single_name . 'IdType',
							],
						],
						'resolve'     => function( $source, array $args, $context, $info ) use ( $taxonomy_object ) {

							$idType  = isset( $args['idType'] ) ? $args['idType'] : 'global_id';
							$term_id = null;

							switch ( $idType ) {
								case 'slug':
								case 'name':
								case 'database_id':
									if ( 'database_id' === $idType ) {
										$idType = 'id';
									}
									$term    = get_term_by( $idType, $args['id'], $taxonomy_object->name );
									$term_id = isset( $term->term_id ) ? absint( $term->term_id ) : null;
									break;
								case 'uri':
									$term = DataSource::resolve_resource_by_uri( $args['id'], $context, $info );
									if ( $term instanceof Term ) {
										$term_id = $term->term_id;
									}
									break;
								case 'global_id':
								default:
									$id_components = Relay::fromGlobalId( $args['id'] );
									if ( ! isset( $id_components['id'] ) || ! absint( $id_components['id'] ) ) {
										throw new UserError( __( 'The ID input is invalid', 'wp-graphql' ) );
									}
									$term_id = absint( $id_components['id'] );
									break;

							}

							return ! empty( $term_id ) ? DataSource::resolve_term_object( $term_id, $context ) : null;
						},
					]
				);
			}
		}

	}
}
