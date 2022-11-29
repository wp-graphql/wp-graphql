<?php

namespace WPGraphQL\Type\Connection;

use Exception;
use GraphQL\Type\Definition\ResolveInfo;
use WP_Post_Type;
use WP_Taxonomy;
use WPGraphQL\AppContext;
use WPGraphQL\Data\Connection\PostObjectConnectionResolver;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\Comment;
use WPGraphQL\Model\Post;
use WPGraphQL\Model\PostType;
use WPGraphQL\Model\User;
use WPGraphQL\Utils\Utils;

/**
 * Class PostObjects
 *
 * This class organizes the registration of connections to PostObjects
 *
 * @package WPGraphQL\Type\Connection
 */
class PostObjects {

	/**
	 * Registers the various connections from other Types to PostObjects
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_connections() {

		register_graphql_connection( [
			'fromType'       => 'ContentType',
			'toType'         => 'ContentNode',
			'fromFieldName'  => 'contentNodes',
			'connectionArgs' => self::get_connection_args(),
			'queryClass'     => 'WP_Query',
			'resolve'        => function ( PostType $post_type, $args, AppContext $context, ResolveInfo $info ) {

				$resolver = new PostObjectConnectionResolver( $post_type, $args, $context, $info );
				$resolver->set_query_arg( 'post_type', $post_type->name );

				return $resolver->get_connection();

			},
		] );

		register_graphql_connection( [
			'fromType'      => 'Comment',
			'toType'        => 'ContentNode',
			'queryClass'    => 'WP_Query',
			'oneToOne'      => true,
			'fromFieldName' => 'commentedOn',
			'resolve'       => function ( Comment $comment, $args, AppContext $context, ResolveInfo $info ) {
				if ( empty( $comment->comment_post_ID ) || ! absint( $comment->comment_post_ID ) ) {
					return null;
				}
				$id       = absint( $comment->comment_post_ID );
				$resolver = new PostObjectConnectionResolver( $comment, $args, $context, $info, 'any' );

				return $resolver->one_to_one()->set_query_arg( 'p', $id )->set_query_arg( 'post_parent', null )->get_connection();
			},
		] );

		register_graphql_connection( [
			'fromType'      => 'NodeWithRevisions',
			'toType'        => 'ContentNode',
			'fromFieldName' => 'revisionOf',
			'description'   => __( 'If the current node is a revision, this field exposes the node this is a revision of. Returns null if the node is not a revision of another node.', 'wp-graphql' ),
			'oneToOne'      => true,
			'resolve'       => function ( Post $post, $args, AppContext $context, ResolveInfo $info ) {

				if ( ! $post->isRevision || ! isset( $post->parentDatabaseId ) || ! absint( $post->parentDatabaseId ) ) {
					return null;
				}

				$resolver = new PostObjectConnectionResolver( $post, $args, $context, $info );
				$resolver->set_query_arg( 'p', $post->parentDatabaseId );

				return $resolver->one_to_one()->get_connection();

			},
		] );

		register_graphql_connection(
			[
				'fromType'       => 'RootQuery',
				'toType'         => 'ContentNode',
				'queryClass'     => 'WP_Query',
				'fromFieldName'  => 'contentNodes',
				'connectionArgs' => self::get_connection_args(),
				'resolve'        => function ( $source, $args, $context, $info ) {
					$post_types = isset( $args['where']['contentTypes'] ) && is_array( $args['where']['contentTypes'] ) ? $args['where']['contentTypes'] : \WPGraphQL::get_allowed_post_types();

					return DataSource::resolve_post_objects_connection( $source, $args, $context, $info, $post_types );
				},
			]
		);

		register_graphql_connection( [
			'fromType'           => 'HierarchicalContentNode',
			'toType'             => 'ContentNode',
			'fromFieldName'      => 'parent',
			'connectionTypeName' => 'HierarchicalContentNodeToParentContentNodeConnection',
			'description'        => __( 'The parent of the node. The parent object can be of various types', 'wp-graphql' ),
			'oneToOne'           => true,
			'resolve'            => function ( Post $post, $args, AppContext $context, ResolveInfo $info ) {

				if ( ! isset( $post->parentDatabaseId ) || ! absint( $post->parentDatabaseId ) ) {
					return null;
				}

				$resolver = new PostObjectConnectionResolver( $post, $args, $context, $info );
				$resolver->set_query_arg( 'p', $post->parentDatabaseId );

				return $resolver->one_to_one()->get_connection();

			},
		] );

		register_graphql_connection( [
			'fromType'           => 'HierarchicalContentNode',
			'fromFieldName'      => 'children',
			'toType'             => 'ContentNode',
			'connectionTypeName' => 'HierarchicalContentNodeToContentNodeChildrenConnection',
			'connectionArgs'     => self::get_connection_args(),
			'queryClass'         => 'WP_Query',
			'resolve'            => function ( Post $post, $args, $context, $info ) {

				if ( $post->isRevision ) {
					$id = $post->parentDatabaseId;
				} else {
					$id = $post->ID;
				}

				$resolver = new PostObjectConnectionResolver( $post, $args, $context, $info, 'any' );
				$resolver->set_query_arg( 'post_parent', $id );

				return $resolver->get_connection();

			},
		] );

		register_graphql_connection( [
			'fromType'           => 'HierarchicalContentNode',
			'toType'             => 'ContentNode',
			'fromFieldName'      => 'ancestors',
			'connectionArgs'     => self::get_connection_args(),
			'connectionTypeName' => 'HierarchicalContentNodeToContentNodeAncestorsConnection',
			'queryClass'         => 'WP_Query',
			'description'        => __( 'Returns ancestors of the node. Default ordered as lowest (closest to the child) to highest (closest to the root).', 'wp-graphql' ),
			'resolve'            => function ( Post $post, $args, $context, $info ) {
				$ancestors = get_ancestors( $post->ID, '', 'post_type' );
				if ( empty( $ancestors ) || ! is_array( $ancestors ) ) {
					return null;
				}
				$resolver = new PostObjectConnectionResolver( $post, $args, $context, $info );
				$resolver->set_query_arg( 'post__in', $ancestors );

				return $resolver->get_connection();
			},
		] );

		/**
		 * Register Connections to PostObjects
		 *
		 * @var \WP_Post_Type[] $allowed_post_types
		 */
		$allowed_post_types = \WPGraphQL::get_allowed_post_types( 'objects' );

		foreach ( $allowed_post_types as $post_type_object ) {

			/**
			 * Registers the RootQuery connection for each post_type
			 */
			if ( true === $post_type_object->graphql_register_root_connection && 'revision' !== $post_type_object->name ) {
				$root_query_from_field_name = Utils::format_field_name( $post_type_object->graphql_plural_name );

				// Prevent field name conflicts with the singular PostObject type.
				if ( $post_type_object->graphql_single_name === $post_type_object->graphql_plural_name ) {
					$root_query_from_field_name = 'all' . ucfirst( $post_type_object->graphql_single_name );
				}

				register_graphql_connection(
					self::get_connection_config(
						$post_type_object,
						[
							'fromFieldName' => $root_query_from_field_name,
						]
					)
				);
			}

			/**
			 * Any post type that supports author should have a connection from User->Author
			 */
			if ( true === post_type_supports( $post_type_object->name, 'author' ) ) {

				/**
				 * Registers the User connection for each post_type
				 */
				register_graphql_connection(
					self::get_connection_config(
						$post_type_object,
						[
							'fromType' => 'User',
							'resolve'  => function ( User $user, $args, AppContext $context, ResolveInfo $info ) use ( $post_type_object ) {
								$resolver = new PostObjectConnectionResolver( $user, $args, $context, $info, $post_type_object->name );
								$resolver->set_query_arg( 'author', $user->userId );

								return $resolver->get_connection();
							},
						]
					)
				);
			}
		}
	}

	/**
	 * Given the Post Type Object and an array of args, this returns an array of args for use in
	 * registering a connection.
	 *
	 * @param mixed|WP_Post_Type|WP_Taxonomy $graphql_object The post type object for the post_type having a
	 *                                        connection registered to it
	 * @param array                          $args           The custom args to modify the connection registration
	 *
	 * @return array
	 */
	public static function get_connection_config( $graphql_object, $args = [] ) {

		$connection_args = self::get_connection_args( [], $graphql_object );

		if ( 'revision' === $graphql_object->name ) {
			unset( $connection_args['status'] );
			unset( $connection_args['stati'] );
		}

		return array_merge(
			[
				'fromType'       => 'RootQuery',
				'toType'         => $graphql_object->graphql_single_name,
				'queryClass'     => 'WP_Query',
				'fromFieldName'  => lcfirst( $graphql_object->graphql_plural_name ),
				'connectionArgs' => $connection_args,
				'resolve'        => function ( $root, $args, $context, $info ) use ( $graphql_object ) {
					return DataSource::resolve_post_objects_connection( $root, $args, $context, $info, $graphql_object->name );
				},
			],
			$args
		);
	}

	/**
	 * Given an optional array of args, this returns the args to be used in the connection
	 *
	 * @param array         $args             The args to modify the defaults
	 * @param mixed|WP_Post_Type|WP_Taxonomy $post_type_object The post type the connection is going to
	 *
	 * @return array
	 */
	public static function get_connection_args( $args = [], $post_type_object = null ) {

		$fields = [
			/**
			 * Search Parameter
			 *
			 * @see   : https://codex.wordpress.org/Class_Reference/WP_Query#Search_Parameter
			 * @since 0.0.5
			 */
			'search'      => [
				'name'        => 'search',
				'type'        => 'String',
				'description' => __( 'Show Posts based on a keyword search', 'wp-graphql' ),
			],

			/**
			 * Post & Page Parameters
			 *
			 * @see   : https://codex.wordpress.org/Class_Reference/WP_Query#Post_.26_Page_Parameters
			 * @since 0.0.5
			 */
			'id'          => [
				'type'        => 'Int',
				'description' => __( 'Specific database ID of the object', 'wp-graphql' ),
			],
			'in'          => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __( 'Array of IDs for the objects to retrieve', 'wp-graphql' ),
			],
			'notIn'       => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __( 'Specify IDs NOT to retrieve. If this is used in the same query as "in", it will be ignored', 'wp-graphql' ),
			],
			'name'        => [
				'type'        => 'String',
				'description' => __( 'Slug / post_name of the object', 'wp-graphql' ),
			],
			'nameIn'      => [
				'type'        => [
					'list_of' => 'String',
				],
				'description' => __( 'Specify objects to retrieve. Use slugs', 'wp-graphql' ),
			],
			'parent'      => [
				'type'        => 'ID',
				'description' => __( 'Use ID to return only children. Use 0 to return only top-level items', 'wp-graphql' ),
			],
			'parentIn'    => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __( 'Specify objects whose parent is in an array', 'wp-graphql' ),
			],
			'parentNotIn' => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __( 'Specify posts whose parent is not in an array', 'wp-graphql' ),
			],
			'title'       => [
				'type'        => 'String',
				'description' => __( 'Title of the object', 'wp-graphql' ),
			],

			/**
			 * Password parameters
			 *
			 * @see   : https://codex.wordpress.org/Class_Reference/WP_Query#Password_Parameters
			 * @since 0.0.2
			 */
			'hasPassword' => [
				'type'        => 'Boolean',
				'description' => __( 'True for objects with passwords; False for objects without passwords; null for all objects with or without passwords', 'wp-graphql' ),
			],
			'password'    => [
				'type'        => 'String',
				'description' => __( 'Show posts with a specific password.', 'wp-graphql' ),
			],

			/**
			 * NOTE: post_type is intentionally not supported on connections to Single post types as
			 * the connection to the singular Post Type already sets this argument as the entry
			 * point to the Graph
			 *
			 * @see   : https://codex.wordpress.org/Class_Reference/WP_Query#Type_Parameters
			 * @since 0.0.2
			 */

			/**
			 * Status parameters
			 *
			 * @see   : https://developer.wordpress.org/reference/classes/wp_query/#status-parameters
			 * @since 0.0.2
			 */
			'status'      => [
				'type'        => 'PostStatusEnum',
				'description' => __( 'Show posts with a specific status.', 'wp-graphql' ),
			],
			'stati'       => [
				'type'        => [
					'list_of' => 'PostStatusEnum',
				],
				'description' => __( 'Retrieve posts where post status is in an array.', 'wp-graphql' ),
			],

			/**
			 * Order & Orderby parameters
			 *
			 * @see   : https://codex.wordpress.org/Class_Reference/WP_Query#Order_.26_Orderby_Parameters
			 * @since 0.0.2
			 */
			'orderby'     => [
				'type'        => [
					'list_of' => 'PostObjectsConnectionOrderbyInput',
				],
				'description' => __( 'What paramater to use to order the objects by.', 'wp-graphql' ),
			],

			/**
			 * Date parameters
			 *
			 * @see https://developer.wordpress.org/reference/classes/wp_query/#date-parameters
			 */
			'dateQuery'   => [
				'type'        => 'DateQueryInput',
				'description' => __( 'Filter the connection based on dates', 'wp-graphql' ),
			],

			/**
			 * Mime type parameters
			 *
			 * @see https://developer.wordpress.org/reference/classes/wp_query/#mime-type-parameters
			 */
			'mimeType'    => [
				'type'        => 'MimeTypeEnum',
				'description' => __( 'Get objects with a specific mimeType property', 'wp-graphql' ),
			],
		];

		/**
		 * If the connection is to a single post type, add additional arguments.
		 *
		 * If the connection is to many post types, the `$post_type_object` will not be an instance
		 * of \WP_Post_Type, and we should not add these additional arguments because it
		 * confuses the connection args for connections of plural post types.
		 *
		 * For example, if you have one Post Type that supports author and another that doesn't
		 * we don't want to expose the `author` filter for a plural connection of multiple post types
		 * as it's misleading to be able to filter by author on a post type that doesn't have
		 * authors.
		 *
		 * If folks want to enable these arguments, they can filter them back in per-connection, but
		 * by default WPGraphQL is exposing the least common denominator (the fields that are shared
		 * by _all_ post types in a multi-post-type connection)
		 *
		 * Here's a practical example:
		 *
		 * Lets's say you register a "House" post type and it doesn't support author.
		 *
		 * The "House" Post Type will show in the `contentNodes` connection, which is a connection
		 * to many post types.
		 *
		 * We could (pseudo code) query like so:
		 *
		 * {
		 *   contentNodes( where: { contentTypes: [ HOUSE ] ) {
		 *     nodes {
		 *       id
		 *       title
		 *       ...on House {
		 *         ...someHouseFields
		 *       }
		 *     }
		 *   }
		 * }
		 *
		 * But since houses don't have authors, it doesn't make sense to have WPGraphQL expose the
		 * ability to query four houses filtered by author.
		 *
		 * ```
		 *{
		 *   contentNodes( where: { author: "some author input" contentTypes: [ HOUSE ] ) {
		 *     nodes {
		 *       id
		 *       title
		 *       ...on House {
		 *         ...someHouseFields
		 *       }
		 *     }
		 *   }
		 * }
		 * ```
		 *
		 * We want to output filters on connections based on what's actually possible, and filtering
		 * houses by author isn't possible, so exposing it in the Schema is quite misleading to
		 * consumers.
		 */
		if ( isset( $post_type_object ) && $post_type_object instanceof WP_Post_Type ) {

			/**
			 * Add arguments to post types that support author
			 */
			if ( true === post_type_supports( $post_type_object->name, 'author' ) ) {
				/**
				 * Author $args
				 *
				 * @see   : https://codex.wordpress.org/Class_Reference/WP_Query#Author_Parameters
				 * @since 0.0.5
				 */
				$fields['author']      = [
					'type'        => 'Int',
					'description' => __( 'The user that\'s connected as the author of the object. Use the userId for the author object.', 'wp-graphql' ),
				];
				$fields['authorName']  = [
					'type'        => 'String',
					'description' => __( 'Find objects connected to the author by the author\'s nicename', 'wp-graphql' ),
				];
				$fields['authorIn']    = [
					'type'        => [
						'list_of' => 'ID',
					],
					'description' => __( 'Find objects connected to author(s) in the array of author\'s userIds', 'wp-graphql' ),
				];
				$fields['authorNotIn'] = [
					'type'        => [
						'list_of' => 'ID',
					],
					'description' => __( 'Find objects NOT connected to author(s) in the array of author\'s userIds', 'wp-graphql' ),
				];
			}

			$connected_taxonomies = get_object_taxonomies( $post_type_object->name );
			if ( ! empty( $connected_taxonomies ) && in_array( 'category', $connected_taxonomies, true ) ) {
				/**
				 * Category $args
				 *
				 * @see   : https://codex.wordpress.org/Class_Reference/WP_Query#Category_Parameters
				 * @since 0.0.5
				 */
				$fields['categoryId']    = [
					'type'        => 'Int',
					'description' => __( 'Category ID', 'wp-graphql' ),
				];
				$fields['categoryName']  = [
					'type'        => 'String',
					'description' => __( 'Use Category Slug', 'wp-graphql' ),
				];
				$fields['categoryIn']    = [
					'type'        => [
						'list_of' => 'ID',
					],
					'description' => __( 'Array of category IDs, used to display objects from one category OR another', 'wp-graphql' ),
				];
				$fields['categoryNotIn'] = [
					'type'        => [
						'list_of' => 'ID',
					],
					'description' => __( 'Array of category IDs, used to display objects from one category OR another', 'wp-graphql' ),
				];
			}

			if ( ! empty( $connected_taxonomies ) && in_array( 'post_tag', $connected_taxonomies, true ) ) {
				/**
				 * Tag $args
				 *
				 * @see   : https://codex.wordpress.org/Class_Reference/WP_Query#Tag_Parameters
				 * @since 0.0.5
				 */
				$fields['tag']        = [
					'type'        => 'String',
					'description' => __( 'Tag Slug', 'wp-graphql' ),
				];
				$fields['tagId']      = [
					'type'        => 'String',
					'description' => __( 'Use Tag ID', 'wp-graphql' ),
				];
				$fields['tagIn']      = [
					'type'        => [
						'list_of' => 'ID',
					],
					'description' => __( 'Array of tag IDs, used to display objects from one tag OR another', 'wp-graphql' ),
				];
				$fields['tagNotIn']   = [
					'type'        => [
						'list_of' => 'ID',
					],
					'description' => __( 'Array of tag IDs, used to display objects from one tag OR another', 'wp-graphql' ),
				];
				$fields['tagSlugAnd'] = [
					'type'        => [
						'list_of' => 'String',
					],
					'description' => __( 'Array of tag slugs, used to display objects from one tag AND another', 'wp-graphql' ),
				];
				$fields['tagSlugIn']  = [
					'type'        => [
						'list_of' => 'String',
					],
					'description' => __( 'Array of tag slugs, used to include objects in ANY specified tags', 'wp-graphql' ),
				];
			}
		} elseif ( $post_type_object instanceof WP_Taxonomy ) {
			/**
			 * Taxonomy-specific Content Type $args
			 *
			 * @see   : https://developer.wordpress.org/reference/classes/wp_query/#post-type-parameters
			 */
			$args['contentTypes'] = [
				'type'        => [ 'list_of' => 'ContentTypesOf' . \WPGraphQL\Utils\Utils::format_type_name( $post_type_object->graphql_single_name ) . 'Enum' ],
				'description' => __( 'The Types of content to filter', 'wp-graphql' ),
			];
		} else {
			/**
			 * Handle cases when the connection is for many post types
			 */

			/**
			 * Content Type $args
			 *
			 * @see   : https://developer.wordpress.org/reference/classes/wp_query/#post-type-parameters
			 */
			$args['contentTypes'] = [
				'type'        => [ 'list_of' => 'ContentTypeEnum' ],
				'description' => __( 'The Types of content to filter', 'wp-graphql' ),
			];
		}

		return array_merge( $fields, $args );
	}
}
