<?php

namespace WPGraphQL\Type\ObjectType;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\Connection\ContentTypeConnectionResolver;
use WPGraphQL\Data\Connection\EnqueuedScriptsConnectionResolver;
use WPGraphQL\Data\Connection\EnqueuedStylesheetConnectionResolver;
use WPGraphQL\Data\Connection\MenuConnectionResolver;
use WPGraphQL\Data\Connection\PostObjectConnectionResolver;
use WPGraphQL\Data\Connection\ThemeConnectionResolver;
use WPGraphQL\Data\Connection\UserRoleConnectionResolver;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\Post;
use WPGraphQL\Type\Connection\PostObjects;
use WPGraphQL\Utils\Utils;

/**
 * Class RootQuery
 *
 * @package WPGraphQL\Type\Object
 */
class RootQuery {

	/**
	 * Register the RootQuery type
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_object_type(
			'RootQuery',
			[
				'description' => static function () {
					return __( 'The root entry point into the Graph', 'wp-graphql' );
				},
				'connections' => [
					'contentTypes'          => [
						'toType'  => 'ContentType',
						'resolve' => static function ( $source, $args, $context, $info ) {
							$resolver = new ContentTypeConnectionResolver( $source, $args, $context, $info );

							return $resolver->get_connection();
						},
					],
					'menus'                 => [
						'toType'         => 'Menu',
						'connectionArgs' => [
							'id'       => [
								'type'        => 'Int',
								'description' => static function () {
									return __( 'The database ID of the object', 'wp-graphql' );
								},
							],
							'location' => [
								'type'        => 'MenuLocationEnum',
								'description' => static function () {
									return __( 'The menu location for the menu being queried', 'wp-graphql' );
								},
							],
							'slug'     => [
								'type'        => 'String',
								'description' => static function () {
									return __( 'The slug of the menu to query items for', 'wp-graphql' );
								},
							],
						],
						'resolve'        => static function ( $source, $args, $context, $info ) {
							$resolver = new MenuConnectionResolver( $source, $args, $context, $info, 'nav_menu' );

							return $resolver->get_connection();
						},
					],
					'plugins'               => [
						'toType'         => 'Plugin',
						'connectionArgs' => [
							'search' => [
								'name'        => 'search',
								'type'        => 'String',
								'description' => static function () {
									return __( 'Show plugin based on a keyword search.', 'wp-graphql' );
								},
							],
							'status' => [
								'type'        => 'PluginStatusEnum',
								'description' => static function () {
									return __( 'Show plugins with a specific status.', 'wp-graphql' );
								},
							],
							'stati'  => [
								'type'        => [ 'list_of' => 'PluginStatusEnum' ],
								'description' => static function () {
									return __( 'Retrieve plugins where plugin status is in an array.', 'wp-graphql' );
								},
							],
						],
						'resolve'        => static function ( $root, $args, $context, $info ) {
							return DataSource::resolve_plugins_connection( $root, $args, $context, $info );
						},
					],
					'registeredScripts'     => [
						'toType'  => 'EnqueuedScript',
						'resolve' => static function ( $source, $args, $context, $info ) {

							// The connection resolver expects the source to include
							// enqueuedScriptsQueue
							$source                       = new \stdClass();
							$source->enqueuedScriptsQueue = [];
							global $wp_scripts;
							do_action( 'wp_enqueue_scripts' );
							$source->enqueuedScriptsQueue = array_keys( $wp_scripts->registered );
							$resolver                     = new EnqueuedScriptsConnectionResolver( $source, $args, $context, $info );

							return $resolver->get_connection();
						},
					],
					'registeredStylesheets' => [
						'toType'  => 'EnqueuedStylesheet',
						'resolve' => static function ( $source, $args, $context, $info ) {

							// The connection resolver expects the source to include
							// enqueuedStylesheetsQueue
							$source                           = new \stdClass();
							$source->enqueuedStylesheetsQueue = [];
							global $wp_styles;
							do_action( 'wp_enqueue_scripts' );
							$source->enqueuedStylesheetsQueue = array_keys( $wp_styles->registered );
							$resolver                         = new EnqueuedStylesheetConnectionResolver( $source, $args, $context, $info );

							return $resolver->get_connection();
						},
					],
					'themes'                => [
						'toType'  => 'Theme',
						'resolve' => static function ( $root, $args, $context, $info ) {
							$resolver = new ThemeConnectionResolver( $root, $args, $context, $info );

							return $resolver->get_connection();
						},
					],
					'revisions'             => [
						'toType'         => 'ContentNode',
						'queryClass'     => 'WP_Query',
						'connectionArgs' => PostObjects::get_connection_args(),
						'resolve'        => static function ( $root, $args, $context, $info ) {
							$resolver = new PostObjectConnectionResolver( $root, $args, $context, $info, 'revision' );

							return $resolver->get_connection();
						},
					],
					'userRoles'             => [
						'toType'        => 'UserRole',
						'fromFieldName' => 'userRoles',
						'resolve'       => static function ( $user, $args, $context, $info ) {
							$resolver = new UserRoleConnectionResolver( $user, $args, $context, $info );

							return $resolver->get_connection();
						},
					],
				],
				'fields'      => static function () {
					return [
						'allSettings' => [
							'type'        => 'Settings',
							'description' => static function () {
								return __( 'Entry point to get all settings for the site', 'wp-graphql' );
							},
							'resolve'     => static function () {
								return true;
							},
						],
						'comment'     => [
							'type'        => 'Comment',
							'description' => static function () {
								return __( 'Returns a Comment', 'wp-graphql' );
							},
							'args'        => [
								'id'     => [
									'type'        => [
										'non_null' => 'ID',
									],
									'description' => static function () {
										return __( 'Unique identifier for the comment node.', 'wp-graphql' );
									},
								],
								'idType' => [
									'type'        => 'CommentNodeIdTypeEnum',
									'description' => static function () {
										return __( 'Type of unique identifier to fetch a comment by. Default is Global ID', 'wp-graphql' );
									},
								],
							],
							'resolve'     => static function ( $_source, array $args, AppContext $context ) {
								$id_type = isset( $args['idType'] ) ? $args['idType'] : 'id';

								switch ( $id_type ) {
									case 'database_id':
										$id = absint( $args['id'] );
										break;
									default:
										$id_components = Relay::fromGlobalId( $args['id'] );
										if ( ! isset( $id_components['id'] ) || ! absint( $id_components['id'] ) ) {
											throw new UserError( esc_html__( 'The ID input is invalid', 'wp-graphql' ) );
										}
										$id = absint( $id_components['id'] );

										break;
								}

								return $context->get_loader( 'comment' )->load_deferred( $id );
							},
						],
						'contentNode' => [
							'type'        => 'ContentNode',
							'description' => static function () {
								return __( 'A node used to manage content', 'wp-graphql' );
							},
							'args'        => [
								'id'          => [
									'type'        => [
										'non_null' => 'ID',
									],
									'description' => static function () {
										return __( 'Unique identifier for the content node.', 'wp-graphql' );
									},
								],
								'idType'      => [
									'type'        => 'ContentNodeIdTypeEnum',
									'description' => static function () {
										return __( 'Type of unique identifier to fetch a content node by. Default is Global ID', 'wp-graphql' );
									},
								],
								'contentType' => [
									'type'        => 'ContentTypeEnum',
									'description' => static function () {
										return __( 'The content type the node is used for. Required when idType is set to "name" or "slug"', 'wp-graphql' );
									},
								],
								'asPreview'   => [
									'type'        => 'Boolean',
									'description' => static function () {
										return __( 'Whether to return the Preview Node instead of the Published Node. When the ID of a Node is provided along with asPreview being set to true, the preview node with un-published changes will be returned instead of the published node. If no preview node exists or the requester doesn\'t have proper capabilities to preview, no node will be returned. If the ID provided is a URI and has a preview query arg, it will be used as a fallback if the "asPreview" argument is not explicitly provided as an argument.', 'wp-graphql' );
									},
								],
							],
							'resolve'     => static function ( $_root, $args, AppContext $context ) {
								$idType = $args['idType'] ?? 'global_id';
								switch ( $idType ) {
									case 'uri':
										return $context->node_resolver->resolve_uri(
											$args['id'],
											[
												'nodeType' => 'ContentNode',
												'asPreview' => $args['asPreview'] ?? null,
											]
										);
									case 'database_id':
										$post_id = absint( $args['id'] );
										break;
									case 'global_id':
									default:
										$id_components = Relay::fromGlobalId( $args['id'] );
										if ( ! isset( $id_components['id'] ) || ! absint( $id_components['id'] ) ) {
											throw new UserError( esc_html__( 'The ID input is invalid. Make sure you set the proper idType for your input.', 'wp-graphql' ) );
										}
										$post_id = absint( $id_components['id'] );
										break;
								}

								if ( isset( $args['asPreview'] ) && true === $args['asPreview'] ) {
									$post_id = Utils::get_post_preview_id( $post_id );
								}

								$allowed_post_types   = \WPGraphQL::get_allowed_post_types();
								$allowed_post_types[] = 'revision';

								return absint( $post_id ) ? $context->get_loader( 'post' )->load_deferred( $post_id )->then(
									static function ( $post ) use ( $allowed_post_types ) {

										// if the post isn't an instance of a Post model, return
										if ( ! $post instanceof Post ) {
											return null;
										}

										if ( ! isset( $post->post_type ) || ! in_array( $post->post_type, $allowed_post_types, true ) ) {
											return null;
										}

										return $post;
									}
								) : null;
							},
						],
						'contentType' => [
							'type'        => 'ContentType',
							'description' => static function () {
								return __( 'Fetch a Content Type node by unique Identifier', 'wp-graphql' );
							},
							'args'        => [
								'id'     => [
									'type'        => [ 'non_null' => 'ID' ],
									'description' => static function () {
										return __( 'Unique Identifier for the Content Type node.', 'wp-graphql' );
									},
								],
								'idType' => [
									'type'        => 'ContentTypeIdTypeEnum',
									'description' => static function () {
										return __( 'Type of unique identifier to fetch a content type by. Default is Global ID', 'wp-graphql' );
									},
								],
							],
							'resolve'     => static function ( $_root, $args, $context ) {
								$id_type = isset( $args['idType'] ) ? $args['idType'] : 'id';

								$id = null;
								switch ( $id_type ) {
									case 'name':
										$id = $args['id'];
										break;
									case 'id':
									default:
										$id_parts = Relay::fromGlobalId( $args['id'] );
										if ( isset( $id_parts['id'] ) ) {
											$id = $id_parts['id'];
										}
								}

								return ! empty( $id ) ? $context->get_loader( 'post_type' )->load_deferred( $id ) : null;
							},
						],
						'taxonomy'    => [
							'type'        => 'Taxonomy',
							'description' => static function () {
								return __( 'Fetch a Taxonomy node by unique Identifier', 'wp-graphql' );
							},
							'args'        => [
								'id'     => [
									'type'        => [ 'non_null' => 'ID' ],
									'description' => static function () {
										return __( 'Unique Identifier for the Taxonomy node.', 'wp-graphql' );
									},
								],
								'idType' => [
									'type'        => 'TaxonomyIdTypeEnum',
									'description' => static function () {
										return __( 'Type of unique identifier to fetch a taxonomy by. Default is Global ID', 'wp-graphql' );
									},
								],
							],
							'resolve'     => static function ( $_root, $args, $context ) {
								$id_type = isset( $args['idType'] ) ? $args['idType'] : 'id';

								$id = null;
								switch ( $id_type ) {
									case 'name':
										$id = $args['id'];
										break;
									case 'id':
									default:
										$id_parts = Relay::fromGlobalId( $args['id'] );
										if ( isset( $id_parts['id'] ) ) {
											$id = $id_parts['id'];
										}
								}

								return ! empty( $id ) ? $context->get_loader( 'taxonomy' )->load_deferred( $id ) : null;
							},
						],
						'node'        => [
							'type'        => 'Node',
							'description' => static function () {
								return __( 'Fetches an object given its ID', 'wp-graphql' );
							},
							'args'        => [
								'id' => [
									'type'        => 'ID',
									'description' => static function () {
										return __( 'The unique identifier of the node', 'wp-graphql' );
									},
								],
							],
							'resolve'     => static function ( $root, $args, AppContext $context, ResolveInfo $info ) {
								return ! empty( $args['id'] ) ? DataSource::resolve_node( $args['id'], $context, $info ) : null;
							},
						],
						'nodeByUri'   => [
							'type'        => 'UniformResourceIdentifiable',
							'description' => static function () {
								return __( 'Fetches an object given its Unique Resource Identifier', 'wp-graphql' );
							},
							'args'        => [
								'uri' => [
									'type'        => [ 'non_null' => 'String' ],
									'description' => static function () {
										return __( 'Unique Resource Identifier in the form of a path or permalink for a node. Ex: "/hello-world"', 'wp-graphql' );
									},
								],
							],
							'resolve'     => static function ( $root, $args, AppContext $context ) {
								return ! empty( $args['uri'] ) ? $context->node_resolver->resolve_uri( $args['uri'] ) : null;
							},
						],
						'menu'        => [
							'type'        => 'Menu',
							'description' => static function () {
								return __( 'A WordPress navigation menu', 'wp-graphql' );
							},
							'args'        => [
								'id'     => [
									'type'        => [
										'non_null' => 'ID',
									],
									'description' => static function () {
										return __( 'The globally unique identifier of the menu.', 'wp-graphql' );
									},
								],
								'idType' => [
									'type'        => 'MenuNodeIdTypeEnum',
									'description' => static function () {
										return __( 'Type of unique identifier to fetch a menu by. Default is Global ID', 'wp-graphql' );
									},
								],
							],
							'resolve'     => static function ( $source, array $args, AppContext $context ) {
								$id_type = isset( $args['idType'] ) ? $args['idType'] : 'id';

								switch ( $id_type ) {
									case 'database_id':
										$id = absint( $args['id'] );
										break;
									case 'location':
										$locations = get_nav_menu_locations();

										if ( ! isset( $locations[ $args['id'] ] ) || ! absint( $locations[ $args['id'] ] ) ) {
											throw new UserError( esc_html__( 'No menu set for the provided location', 'wp-graphql' ) );
										}

										$id = absint( $locations[ $args['id'] ] );
										break;
									case 'name':
										$menu = new \WP_Term_Query(
											[
												'taxonomy' => 'nav_menu',
												'fields'   => 'ids',
												'name'     => $args['id'],
												'include_children' => false,
												'count'    => false,
											]
										);
										$id   = ! empty( $menu->terms ) ? (int) $menu->terms[0] : null;
										break;
									case 'slug':
										$menu = new \WP_Term_Query(
											[
												'taxonomy' => 'nav_menu',
												'fields'   => 'ids',
												'slug'     => $args['id'],
												'include_children' => false,
												'count'    => false,
											]
										);
										$id   = ! empty( $menu->terms ) ? (int) $menu->terms[0] : null;
										break;
									default:
										$id_components = Relay::fromGlobalId( $args['id'] );
										if ( ! isset( $id_components['id'] ) || ! absint( $id_components['id'] ) ) {
											throw new UserError( esc_html__( 'The ID input is invalid', 'wp-graphql' ) );
										}
										$id = absint( $id_components['id'] );

										break;
								}

								return ! empty( $id ) ? $context->get_loader( 'term' )->load_deferred( absint( $id ) ) : null;
							},
						],
						'menuItem'    => [
							'type'        => 'MenuItem',
							'description' => static function () {
								return __( 'A WordPress navigation menu item', 'wp-graphql' );
							},
							'args'        => [
								'id'     => [
									'type'        => [
										'non_null' => 'ID',
									],
									'description' => static function () {
										return __( 'The globally unique identifier of the menu item.', 'wp-graphql' );
									},
								],
								'idType' => [
									'type'        => 'MenuItemNodeIdTypeEnum',
									'description' => static function () {
										return __( 'Type of unique identifier to fetch a menu item by. Default is Global ID', 'wp-graphql' );
									},
								],
							],
							'resolve'     => static function ( $source, array $args, AppContext $context ) {
								$id_type = isset( $args['idType'] ) ? $args['idType'] : 'id';

								switch ( $id_type ) {
									case 'database_id':
										$id = absint( $args['id'] );
										break;
									default:
										$id_components = Relay::fromGlobalId( $args['id'] );
										if ( ! isset( $id_components['id'] ) || ! absint( $id_components['id'] ) ) {
											throw new UserError( esc_html__( 'The ID input is invalid', 'wp-graphql' ) );
										}
										$id = absint( $id_components['id'] );

										break;
								}

								return $context->get_loader( 'post' )->load_deferred( absint( $id ) );
							},
						],
						'plugin'      => [
							'type'        => 'Plugin',
							'description' => static function () {
								return __( 'A WordPress plugin', 'wp-graphql' );
							},
							'args'        => [
								'id' => [
									'type'        => [
										'non_null' => 'ID',
									],
									'description' => static function () {
										return __( 'The globally unique identifier of the plugin.', 'wp-graphql' );
									},
								],
							],
							'resolve'     => static function ( $source, array $args, AppContext $context ) {
								$id_components = Relay::fromGlobalId( $args['id'] );

								return ! empty( $id_components['id'] ) ? $context->get_loader( 'plugin' )->load_deferred( $id_components['id'] ) : null;
							},
						],
						'termNode'    => [
							'type'        => 'TermNode',
							'description' => static function () {
								return __( 'A node in a taxonomy used to group and relate content nodes', 'wp-graphql' );
							},
							'args'        => [
								'id'       => [
									'type'        => [
										'non_null' => 'ID',
									],
									'description' => static function () {
										return __( 'Unique identifier for the term node.', 'wp-graphql' );
									},
								],
								'idType'   => [
									'type'        => 'TermNodeIdTypeEnum',
									'description' => static function () {
										return __( 'Type of unique identifier to fetch a term node by. Default is Global ID', 'wp-graphql' );
									},
								],
								'taxonomy' => [
									'type'        => 'TaxonomyEnum',
									'description' => static function () {
										return __( 'The taxonomy of the tern node. Required when idType is set to "name" or "slug"', 'wp-graphql' );
									},
								],
							],
							'resolve'     => static function ( $root, $args, AppContext $context ) {
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
											throw new UserError( esc_html__( 'When fetching a Term Node by "slug" or "name", the "taxonomy" also needs to be set as an input.', 'wp-graphql' ) );
										}
										if ( 'database_id' === $idType ) {
											$term = get_term( absint( $args['id'] ) );
										} else {
											$term = get_term_by( $idType, $args['id'], $taxonomy );
										}
										$term_id = isset( $term->term_id ) ? absint( $term->term_id ) : null;

										break;
									case 'uri':
										return $context->node_resolver->resolve_uri(
											$args['id'],
											[
												'nodeType' => 'TermNode',
											]
										);
									case 'global_id':
									default:
										$id_components = Relay::fromGlobalId( $args['id'] );
										if ( ! isset( $id_components['id'] ) || ! absint( $id_components['id'] ) ) {
											throw new UserError( esc_html__( 'The ID input is invalid', 'wp-graphql' ) );
										}
										$term_id = absint( $id_components['id'] );
										break;
								}

								return ! empty( $term_id ) ? $context->get_loader( 'term' )->load_deferred( $term_id ) : null;
							},
						],
						'theme'       => [
							'type'        => 'Theme',
							'description' => static function () {
								return __( 'A Theme object', 'wp-graphql' );
							},
							'args'        => [
								'id' => [
									'type'        => [
										'non_null' => 'ID',
									],
									'description' => static function () {
										return __( 'The globally unique identifier of the theme.', 'wp-graphql' );
									},
								],
							],
							'resolve'     => static function ( $source, array $args ) {
								$id_components = Relay::fromGlobalId( $args['id'] );

								return DataSource::resolve_theme( $id_components['id'] );
							},
						],
						'user'        => [
							'type'        => 'User',
							'description' => static function () {
								return __( 'Returns a user', 'wp-graphql' );
							},
							'args'        => [
								'id'     => [
									'type'        => [
										'non_null' => 'ID',
									],
									'description' => static function () {
										return __( 'The globally unique identifier of the user.', 'wp-graphql' );
									},
								],
								'idType' => [
									'type'        => 'UserNodeIdTypeEnum',
									'description' => static function () {
										return __( 'Type of unique identifier to fetch a user by. Default is Global ID', 'wp-graphql' );
									},
								],
							],
							'resolve'     => static function ( $source, array $args, $context ) {
								$idType = isset( $args['idType'] ) ? $args['idType'] : 'id';

								switch ( $idType ) {
									case 'database_id':
										$id = absint( $args['id'] );
										break;
									case 'uri':
										return $context->node_resolver->resolve_uri(
											$args['id'],
											[
												'nodeType' => 'User',
											]
										);
									case 'login':
										$current_user = wp_get_current_user();
										if ( $current_user->user_login !== $args['id'] ) {
											if ( ! current_user_can( 'list_users' ) ) {
												throw new UserError( esc_html__( 'You do not have permission to request a User by Username', 'wp-graphql' ) );
											}
										}

										$user = get_user_by( 'login', $args['id'] );
										$id   = isset( $user->ID ) ? $user->ID : null;
										break;
									case 'email':
										$current_user = wp_get_current_user();
										if ( $current_user->user_email !== $args['id'] ) {
											if ( ! current_user_can( 'list_users' ) ) {
												throw new UserError( esc_html__( 'You do not have permission to request a User by Email', 'wp-graphql' ) );
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

								return ! empty( $id ) ? $context->get_loader( 'user' )->load_deferred( $id ) : null;
							},
						],
						'userRole'    => [
							'type'        => 'UserRole',
							'description' => static function () {
								return __( 'Returns a user role', 'wp-graphql' );
							},
							'args'        => [
								'id' => [
									'type'        => [
										'non_null' => 'ID',
									],
									'description' => static function () {
										return __( 'The globally unique identifier of the user object.', 'wp-graphql' );
									},
								],
							],
							'resolve'     => static function ( $source, array $args ) {
								$id_components = Relay::fromGlobalId( $args['id'] );

								return DataSource::resolve_user_role( $id_components['id'] );
							},
						],
						'viewer'      => [
							'type'        => 'User',
							'description' => static function () {
								return __( 'Returns the current user', 'wp-graphql' );
							},
							'resolve'     => static function ( $source, array $args, AppContext $context ) {
								return ! empty( $context->viewer->ID ) ? $context->get_loader( 'user' )->load_deferred( $context->viewer->ID ) : null;
							},
						],
					];
				},
			]
		);
	}

	/**
	 * Register RootQuery fields for Post Objects of supported post types
	 *
	 * @return void
	 */
	public static function register_post_object_fields() {
		$allowed_post_types = \WPGraphQL::get_allowed_post_types( 'objects', [ 'graphql_register_root_field' => true ] );

		foreach ( $allowed_post_types as $post_type_object ) {
			register_graphql_field(
				'RootQuery',
				$post_type_object->graphql_single_name,
				[
					'type'        => $post_type_object->graphql_single_name,
					'description' => static function () use ( $post_type_object ) {
						return sprintf(
							// translators: %1$s is the post type GraphQL name, %2$s is the post type description
							__( 'An object of the %1$s Type. %2$s', 'wp-graphql' ),
							$post_type_object->graphql_single_name,
							$post_type_object->description
						);
					},
					'args'        => [
						'id'        => [
							'type'        => [
								'non_null' => 'ID',
							],
							'description' => static function () {
								return __( 'The globally unique identifier of the object.', 'wp-graphql' );
							},
						],
						'idType'    => [
							'type'        => $post_type_object->graphql_single_name . 'IdType',
							'description' => static function () {
								return __( 'Type of unique identifier to fetch by. Default is Global ID', 'wp-graphql' );
							},
						],
						'asPreview' => [
							'type'        => 'Boolean',
							'description' => static function () {
								return __( 'Whether to return the Preview Node instead of the Published Node. When the ID of a Node is provided along with asPreview being set to true, the preview node with un-published changes will be returned instead of the published node. If no preview node exists or the requester doesn\'t have proper capabilities to preview, no node will be returned. If the ID provided is a URI and has a preview query arg, it will be used as a fallback if the "asPreview" argument is not explicitly provided as an argument.', 'wp-graphql' );
							},
						],
					],
					'resolve'     => static function ( $source, array $args, AppContext $context ) use ( $post_type_object ) {
						$idType  = isset( $args['idType'] ) ? $args['idType'] : 'global_id';
						$post_id = null;
						switch ( $idType ) {
							case 'slug':
								return $context->node_resolver->resolve_uri(
									$args['id'],
									[
										'name'      => $args['id'],
										'post_type' => $post_type_object->name,
										'nodeType'  => 'ContentNode',
										'asPreview' => $args['asPreview'] ?? null,
									]
								);
							case 'uri':
								return $context->node_resolver->resolve_uri(
									$args['id'],
									[
										'post_type' => $post_type_object->name,
										'archive'   => false,
										'nodeType'  => 'ContentNode',
										'asPreview' => $args['asPreview'] ?? null,
									]
								);
							case 'database_id':
								$post_id = absint( $args['id'] );
								break;
							case 'source_url':
								$url     = $args['id'];
								$post_id = attachment_url_to_postid( $url ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.attachment_url_to_postid_attachment_url_to_postid
								if ( empty( $post_id ) ) {
									return null;
								}
								$post_id = absint( attachment_url_to_postid( $url ) ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.attachment_url_to_postid_attachment_url_to_postid
								break;
							case 'global_id':
							default:
								$id_components = Relay::fromGlobalId( $args['id'] );
								if ( ! isset( $id_components['id'] ) || ! absint( $id_components['id'] ) ) {
									throw new UserError( esc_html__( 'The ID input is invalid. Make sure you set the proper idType for your input.', 'wp-graphql' ) );
								}
								$post_id = absint( $id_components['id'] );
								break;
						}

						if ( isset( $args['asPreview'] ) && true === $args['asPreview'] ) {
							$post_id = Utils::get_post_preview_id( $post_id );
						}

						return absint( $post_id ) ? $context->get_loader( 'post' )->load_deferred( $post_id )->then(
							static function ( $post ) use ( $post_type_object ) {

								// if the post isn't an instance of a Post model, return
								if ( ! $post instanceof Post ) {
									return null;
								}

								if ( ! isset( $post->post_type ) || ! in_array(
									$post->post_type,
									[
										'revision',
										$post_type_object->name,
									],
									true
								) ) {
									return null;
								}

								return $post;
							}
						) : null;
					},
				]
			);
			$post_by_args = [
				'id'  => [
					'type'        => 'ID',
					'description' => static function () use ( $post_type_object ) {
						return sprintf(
							// translators: %s is the post type's GraphQL name.
							__( 'Get the %s object by its global ID', 'wp-graphql' ),
							$post_type_object->graphql_single_name
						);
					},
				],
				$post_type_object->graphql_single_name . 'Id' => [
					'type'        => 'Int',
					'description' => static function () use ( $post_type_object ) {
						return sprintf(
							// translators: %s is the post type's GraphQL name.
							__( 'Get the %s by its database ID', 'wp-graphql' ),
							$post_type_object->graphql_single_name
						);
					},
				],
				'uri' => [
					'type'        => 'String',
					'description' => static function () use ( $post_type_object ) {
						return sprintf(
							// translators: %s is the post type's GraphQL name.
							__( 'Get the %s by its uri', 'wp-graphql' ),
							$post_type_object->graphql_single_name
						);
					},
				],
			];
			if ( false === $post_type_object->hierarchical ) {
				$post_by_args['slug'] = [
					'type'        => 'String',
					'description' => static function () use ( $post_type_object ) {
						return sprintf(
							// translators: %s is the post type's GraphQL name.
							__( 'Get the %s by its slug (only available for non-hierarchical types)', 'wp-graphql' ),
							$post_type_object->graphql_single_name
						);
					},
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
					'deprecationReason' => static function () {
						return __( 'Deprecated in favor of using the single entry point for this type with ID and IDType fields. For example, instead of postBy( id: "" ), use post(id: "" idType: "")', 'wp-graphql' );
					},
					'description'       => static function () use ( $post_type_object ) {
						return sprintf(
							// translators: %s is the post type's GraphQL name.
							__( 'A %s object', 'wp-graphql' ),
							$post_type_object->graphql_single_name
						);
					},
					'args'              => $post_by_args,
					'resolve'           => static function ( $source, array $args, $context ) use ( $post_type_object ) {
						$post_id = 0;

						if ( ! empty( $args['id'] ) ) {
							$id_components = Relay::fromGlobalId( $args['id'] );
							if ( empty( $id_components['id'] ) || empty( $id_components['type'] ) ) {
								throw new UserError( esc_html__( 'The "id" is invalid', 'wp-graphql' ) );
							}
							$post_id = absint( $id_components['id'] );
						} elseif ( ! empty( $args[ lcfirst( $post_type_object->graphql_single_name . 'Id' ) ] ) ) {
							$id      = $args[ lcfirst( $post_type_object->graphql_single_name . 'Id' ) ];
							$post_id = absint( $id );
						} elseif ( ! empty( $args['uri'] ) ) {
							return $context->node_resolver->resolve_uri(
								$args['uri'],
								[
									'post_type' => $post_type_object->name,
									'archive'   => false,
									'nodeType'  => 'ContentNode',
								]
							);
						} elseif ( ! empty( $args['slug'] ) ) {
							$slug = esc_html( $args['slug'] );

							return $context->node_resolver->resolve_uri(
								$slug,
								[
									'name'      => $slug,
									'post_type' => $post_type_object->name,
									'nodeType'  => 'ContentNode',
								]
							);
						}

						return $context->get_loader( 'post' )->load_deferred( $post_id )->then(
							static function ( $post ) use ( $post_type_object ) {

								// if the post type object isn't an instance of WP_Post_Type, return
								if ( ! $post_type_object instanceof \WP_Post_Type ) {
									return null;
								}

								// if the post isn't an instance of a Post model, return
								if ( ! $post instanceof Post ) {
									return null;
								}

								if ( ! isset( $post->post_type ) || ! in_array(
									$post->post_type,
									[
										'revision',
										$post_type_object->name,
									],
									true
								) ) {
									return null;
								}

								return $post;
							}
						);
					},
				]
			);
		}
	}

	/**
	 * Register RootQuery fields for Term Objects of supported taxonomies
	 *
	 * @return void
	 */
	public static function register_term_object_fields() {
		$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies( 'objects', [ 'graphql_register_root_field' => true ] );

		foreach ( $allowed_taxonomies as $tax_object ) {
			register_graphql_field(
				'RootQuery',
				$tax_object->graphql_single_name,
				[
					'type'        => $tax_object->graphql_single_name,
					'description' => static function () use ( $tax_object ) {
						return sprintf(
							// translators: %s is the taxonomys' GraphQL name.
							__( 'A % object', 'wp-graphql' ),
							$tax_object->graphql_single_name
						);
					},
					'args'        => [
						'id'     => [
							'type'        => [
								'non_null' => 'ID',
							],
							'description' => static function () {
								return __( 'The globally unique identifier of the object.', 'wp-graphql' );
							},
						],
						'idType' => [
							'type'        => $tax_object->graphql_single_name . 'IdType',
							'description' => static function () {
								return __( 'Type of unique identifier to fetch by. Default is Global ID', 'wp-graphql' );
							},
						],
					],
					'resolve'     => static function ( $_source, array $args, $context ) use ( $tax_object ) {
						$idType  = isset( $args['idType'] ) ? $args['idType'] : 'global_id';
						$term_id = null;

						switch ( $idType ) {
							case 'slug':
							case 'name':
							case 'database_id':
								if ( 'database_id' === $idType ) {
									$idType = 'id';
								}
								$term    = get_term_by( $idType, $args['id'], $tax_object->name );
								$term_id = isset( $term->term_id ) ? absint( $term->term_id ) : null;
								break;
							case 'uri':
								return $context->node_resolver->resolve_uri(
									$args['id'],
									[
										'nodeType' => 'TermNode',
										'taxonomy' => $tax_object->name,
									]
								);
							case 'global_id':
							default:
								$id_components = Relay::fromGlobalId( $args['id'] );
								if ( ! isset( $id_components['id'] ) || ! absint( $id_components['id'] ) ) {
									throw new UserError( esc_html__( 'The ID input is invalid', 'wp-graphql' ) );
								}
								$term_id = absint( $id_components['id'] );
								break;
						}

						return ! empty( $term_id ) ? $context->get_loader( 'term' )->load_deferred( (int) $term_id ) : null;
					},
				]
			);
		}
	}
}
