<?php

namespace WPGraphQL\Connection;

use WPGraphQL\Data\DataSource;

/**
 * Class PostObjects
 *
 * This class organizes the registration of connections to PostObjects
 *
 * @package WPGraphQL\Connection
 */
class PostObjects {

	/**
	 * Registers the various connections from other Types to PostObjects
	 */
	public static function register_connections() {

		/**
		 * Register Connections to PostObjects
		 */
		$allowed_post_types = \WPGraphQL::$allowed_post_types;
		if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {
			foreach ( $allowed_post_types as $post_type ) {

				$post_type_object = get_post_type_object( $post_type );

				/**
				 * Registers the RootQuery connection for each post_type
				 */
				register_graphql_connection( self::get_connection_config( $post_type_object ) );

				/**
				 * Registers the User connection for each post_type
				 */
				register_graphql_connection( self::get_connection_config( $post_type_object, [
					'fromType' => 'User'
				] ) );

				/**
				 * Registers connections for each post_type that has a connection
				 * to a taxonomy that's allowed in GraphQL
				 */
				$allowed_taxonomies = \WPGraphQL::$allowed_taxonomies;
				if ( ! empty( $allowed_taxonomies ) && is_array( $allowed_taxonomies ) ) {
					foreach ( $allowed_taxonomies as $taxonomy ) {
						// If the taxonomy is in the array of taxonomies registered to the post_type
						if ( in_array( $taxonomy, get_object_taxonomies( $post_type_object->name ), true ) ) {
							$tax_object = get_taxonomy( $taxonomy );
							register_graphql_connection( self::get_connection_config( $post_type_object, [
								'fromType' => $tax_object->graphql_single_name,
							] ) );
						}
					}
				}

				/**
				 * Registers the connection to child items if the post_type is hierarchical
				 */
				if ( true === $post_type_object->hierarchical ) {
					register_graphql_connection( self::get_connection_config( $post_type_object, [
						'fromType'      => $post_type_object->graphql_single_name,
						'fromFieldName' => 'child' . ucfirst( $post_type_object->graphql_plural_name ),
					] ) );
				}

			}
		}

	}

	/**
	 * Given the Post Type Object and an array of args, this returns an array of args for use in
	 * registering a connection.
	 *
	 * @param \WP_Post_Type $post_type_object The post type object for the post_type having a
	 *                                        connection registered to it
	 * @param array         $args             The custom args to modify the connection registration
	 *
	 * @return array
	 */
	protected static function get_connection_config( $post_type_object, $args = [] ) {
		return array_merge( [
			'fromType'         => 'RootQuery',
			'toType'           => $post_type_object->graphql_single_name,
			'queryClass'       => 'WP_Query',
			'connectionFields' => [
				'postTypeInfo' => [
					'type'        => 'PostType',
					'description' => __( 'Information about the type of content being queried', 'wp-graphql' ),
					'resolve'     => function ( $source, array $args, $context, $info ) use ( $post_type_object ) {
						return $post_type_object;
					},
				],
			],
			'fromFieldName'    => lcfirst( $post_type_object->graphql_plural_name ),
			'connectionArgs'   => self::get_connection_args(),
			'resolve'          => function ( $root, $args, $context, $info ) use ( $post_type_object ) {
				return DataSource::resolve_post_objects_connection( $root, $args, $context, $info, $post_type_object->name );
			},
		], $args );
	}

	/**
	 * Given an optional array of args, this returns the args to be used in the connection
	 *
	 * @param array $args The args to modify the defaults
	 *
	 * @return array
	 */
	protected static function get_connection_args( $args = [] ) {

		return array_merge( [

			/**
			 * Author $args
			 *
			 * @see   : https://codex.wordpress.org/Class_Reference/WP_Query#Author_Parameters
			 * @since 0.0.5
			 */
			'author'       => [
				'type'        => 'Int',
				'description' => __( 'The user that\'s connected as the author of the object. Use the
							userId for the author object.', 'wp-graphql' ),
			],
			'authorName'   => [
				'type'        => 'String',
				'description' => __( 'Find objects connected to the author by the author\'s nicename', 'wp-graphql' ),
			],
			'authorIn'     => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __( 'Find objects connected to author(s) in the array of author\'s userIds', 'wp-graphql' ),
			],
			'authorNotIn'  => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __( 'Find objects NOT connected to author(s) in the array of author\'s
							userIds', 'wp-graphql' ),
			],

			/**
			 * Category $args
			 *
			 * @see   : https://codex.wordpress.org/Class_Reference/WP_Query#Category_Parameters
			 * @since 0.0.5
			 */
			'categoryId'   => [
				'type'        => 'Int',
				'description' => __( 'Category ID', 'wp-graphql' ),
			],
			'categoryName' => [
				'type'        => 'String',
				'description' => __( 'Use Category Slug', 'wp-graphql' ),
			],
			'categoryIn'   => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __( 'Array of category IDs, used to display objects from one
										category OR another', 'wp-graphql' ),
			],

			/**
			 * Tag $args
			 *
			 * @see   : https://codex.wordpress.org/Class_Reference/WP_Query#Tag_Parameters
			 * @since 0.0.5
			 */
			'tag'          => [
				'type'        => 'String',
				'description' => __( 'Tag Slug', 'wp-graphql' ),
			],
			'tagId'        => [
				'type'        => 'String',
				'description' => __( 'Use Tag ID', 'wp-graphql' ),
			],
			'tagIn'        => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __( 'Array of tag IDs, used to display objects from one tag OR
							another', 'wp-graphql' ),
			],
			'tagSlugAnd'   => [
				'type'        => [
					'list_of' => 'String',
				],
				'description' => __( 'Array of tag slugs, used to display objects from one tag OR
							another', 'wp-graphql' ),
			],
			'tagSlugIn'    => [
				'type'        => [
					'list_of' => 'String',
				],
				'description' => __( 'Array of tag slugs, used to exclude objects in specified
							tags', 'wp-graphql' ),
			],

			/**
			 * Search Parameter
			 *
			 * @see   : https://codex.wordpress.org/Class_Reference/WP_Query#Search_Parameter
			 * @since 0.0.5
			 */
			'search'       => [
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
			'id'           => [
				'type'        => 'Int',
				'description' => __( 'Specific ID of the object', 'wp-graphql' ),
			],
			'name'         => [
				'type'        => 'String',
				'description' => __( 'Slug / post_name of the object', 'wp-graphql' ),
			],
			'title'        => [
				'type'        => 'String',
				'description' => __( 'Title of the object', 'wp-graphql' ),
			],
			'parent'       => [
				'type'        => 'String',
				'description' => __( 'Use ID to return only children. Use 0 to return only top-level
							items', 'wp-graphql' ),
			],
			'parentIn'     => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __( 'Specify objects whose parent is in an array', 'wp-graphql' ),
			],
			'parentNotIn'  => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __( 'Specify posts whose parent is not in an array', 'wp-graphql' ),
			],
			'in'           => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __( 'Array of IDs for the objects to retrieve', 'wp-graphql' ),
			],
			'notIn'        => [
				'type'        => [
					'list_of' => 'ID',
				],
				'description' => __( 'Specify IDs NOT to retrieve. If this is used in the same query as "in",
							it will be ignored', 'wp-graphql' ),
			],
			'nameIn'       => [
				'type'        => [
					'list_of' => 'String',
				],
				'description' => __( 'Specify objects to retrieve. Use slugs', 'wp-graphql' ),
			],

			/**
			 * Password parameters
			 *
			 * @see   : https://codex.wordpress.org/Class_Reference/WP_Query#Password_Parameters
			 * @since 0.0.2
			 */
			'hasPassword'  => [
				'type'        => 'Boolean',
				'description' => __( 'True for objects with passwords; False for objects without passwords;
							null for all objects with or without passwords', 'wp-graphql' ),
			],
			'password'     => [
				'type'        => 'String',
				'description' => __( 'Show posts with a specific password.', 'wp-graphql' ),
			],

			/**
			 * post_type
			 * NOTE: post_type is intentionally not supported as it's the post_type is the entity entry
			 * point for the queries
			 *
			 * @see   : https://codex.wordpress.org/Class_Reference/WP_Query#Type_Parameters
			 * @since 0.0.2
			 */

			/**
			 * Status parameters
			 *
			 * @see   : https://codex.wordpress.org/Class_Reference/WP_Query#Status_Parameters
			 * @since 0.0.2
			 */
			'status'       => [
				'type' => 'PostStatusEnum',
			],

			/**
			 * List of post status parameters
			 */
			'stati'        => [
				'type' => [
					'list_of' => 'PostStatusEnum',
				],
			],

			/**
			 * Order & Orderby parameters
			 *
			 * @see   : https://codex.wordpress.org/Class_Reference/WP_Query#Order_.26_Orderby_Parameters
			 * @since 0.0.2
			 */
			'orderby'      => [
				'type'        => [
					'list_of' => 'PostObjectsConnectionOrderbyInput',
				],
				'description' => __( 'What paramater to use to order the objects by.', 'wp-graphql' ),
			],
			'dateQuery'    => [
				'type'        => 'DateQueryInput',
				'description' => __( 'Filter the connection based on dates', 'wp-graphql' ),
			],
			'mimeType'     => [
				'type'        => 'MimeTypeEnum',
				'description' => __( 'Get objects with a specific mimeType property', 'wp-graphql' ),
			],
		], $args );
	}
}