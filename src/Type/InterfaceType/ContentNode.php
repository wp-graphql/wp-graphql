<?php
namespace WPGraphQL\Type\InterfaceType;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\Post;
use WPGraphQL\Registry\TypeRegistry;

class ContentNode {

	/**
	 * Adds the ContentNode Type to the WPGraphQL Registry
	 * @param TypeRegistry $type_registry
	 */
	public static function register_type( TypeRegistry $type_registry ) {

		/**
		 * The Content interface represents Post Types and the common shared fields
		 * across Post Type Objects
		 */
		register_graphql_interface_type( 'ContentNode', [
			'description' => __( 'Object used to manage content', 'wp-graphql' ),
			'resolveType' => function( $post ) use ( $type_registry ) {

				/**
				 * The resolveType callback is used at runtime to determine what Type an object
				 * implementing the ContentNode Interface should be resolved as.
				 *
				 * You can filter this centrally using the "graphql_wp_interface_type_config" filter
				 * to override if you need something other than a Post object to be resolved via the
				 * $post->post_type attribute.
				 */
				$type = null;

				if ( isset( $post->post_type ) ) {
					$post_type_object = get_post_type_object( $post->post_type );
					if ( isset( $post_type_object->graphql_single_name ) ) {
						$type = $type_registry->get_type( $post_type_object->graphql_single_name );
					}
				}

				return ! empty( $type ) ? $type : null;

			},
			'fields' => [
				'id'                => [
					'type' => [
						'non_null' => 'ID',
					],
					'description' => __( 'The globally unique identifier of the entity.', 'wp-graphql' ),
				],
				'databaseId' => [
					'type'        => [
						'non_null' => 'Int',
					],
					'description' => __( 'The ID of the object in the database.', 'wp-graphql' ),
					'resolve'     => function( Post $post, $args, $context, $info ) {
						return absint( $post->ID );
					},
				],
				'isRevision' => [
					'type' => 'Boolean',
					'description' => __( 'Whether the object is a revision', 'wp-graphql' ),
					'resolve' => function( Post $post, $args, $context, $info ) {
						return 'revision' === $post->post_type ? true : false;
					}
				],
				'author'            => [
					'type'        => 'User',
					'description' => __( "The author field will return a queryable User type matching the post's author.", 'wp-graphql' ),
					'resolve'     => function( Post $post, $args, AppContext $context, ResolveInfo $info ) {
						// @codingStandardsIgnoreLine.
						if ( ! isset( $post->authorId ) || ! absint( $post->authorId ) ) {
							return null;
						};

						// @codingStandardsIgnoreLine.
						return DataSource::resolve_user( $post->authorId, $context );
					},
				],
				'date'              => [
					'type'        => 'String',
					'description' => __( 'Post publishing date.', 'wp-graphql' ),
				],
				'dateGmt'           => [
					'type'        => 'String',
					'description' => __( 'The publishing date set in GMT.', 'wp-graphql' ),
				],
				'content'           => [
					'type'        => 'String',
					'description' => __( 'The content of the post.', 'wp-graphql' ),
					'args'        => [
						'format' => [
							'type'        => 'PostObjectFieldFormatEnum',
							'description' => __( 'Format of the field output', 'wp-graphql' ),
						],
					],
					'resolve'     => function( $source, $args ) {
						if ( isset( $args['format'] ) && 'raw' === $args['format'] ) {
							// @codingStandardsIgnoreLine.
							return $source->contentRaw;
						}

						// @codingStandardsIgnoreLine.
						return $source->contentRendered;
					},
				],
				'title'             => [
					'type'        => 'String',
					'description' => __( 'The title of the post. This is currently just the raw title. An amendment to support rendered title needs to be made.', 'wp-graphql' ),
					'args'        => [
						'format' => [
							'type'        => 'PostObjectFieldFormatEnum',
							'description' => __( 'Format of the field output', 'wp-graphql' ),
						],
					],
					'resolve'     => function( $source, $args ) {
						if ( isset( $args['format'] ) && 'raw' === $args['format'] ) {
							// @codingStandardsIgnoreLine.
							return $source->titleRaw;
						}

						// @codingStandardsIgnoreLine.
						return $source->titleRendered;
					},
				],
				'excerpt'           => [
					'type'        => 'String',
					'description' => __( 'The excerpt of the post.', 'wp-graphql' ),
					'args'        => [
						'format' => [
							'type'        => 'PostObjectFieldFormatEnum',
							'description' => __( 'Format of the field output', 'wp-graphql' ),
						],
					],
					'resolve'     => function( $source, $args ) {
						if ( isset( $args['format'] ) && 'raw' === $args['format'] ) {
							// @codingStandardsIgnoreLine.
							return $source->excerptRaw;
						}

						// @codingStandardsIgnoreLine.
						return $source->excerptRendered;
					},
				],
				'status'            => [
					'type'        => 'String',
					'description' => __( 'The current status of the object', 'wp-graphql' ),
				],
				'commentStatus'     => [
					'type'        => 'String',
					'description' => __( 'Whether the comments are open or closed for this particular post.', 'wp-graphql' ),
				],
				'pingStatus'        => [
					'type'        => 'String',
					'description' => __( 'Whether the pings are open or closed for this particular post.', 'wp-graphql' ),
				],
				'slug'              => [
					'type'        => 'String',
					'description' => __( 'The uri slug for the post. This is equivalent to the WP_Post->post_name field and the post_name column in the database for the "post_objects" table.', 'wp-graphql' ),
				],
				'toPing'            => [
					'type'        => [ 'list_of' => 'String' ],
					'description' => __( 'URLs queued to be pinged.', 'wp-graphql' ),
				],
				'pinged'            => [
					'type'        => [ 'list_of' => 'String' ],
					'description' => __( 'URLs that have been pinged.', 'wp-graphql' ),
				],
				'modified'          => [
					'type'        => 'String',
					'description' => __( 'The local modified time for a post. If a post was recently updated the modified field will change to match the corresponding time.', 'wp-graphql' ),
				],
				'modifiedGmt'       => [
					'type'        => 'String',
					'description' => __( 'The GMT modified time for a post. If a post was recently updated the modified field will change to match the corresponding time in GMT.', 'wp-graphql' ),
				],
				'editLast'          => [
					'type'        => 'User',
					'description' => __( 'The user that most recently edited the object', 'wp-graphql' ),
					'resolve'     => function( Post $post, $args, AppContext $context, ResolveInfo $info ) {
						// @codingStandardsIgnoreLine.
						if ( ! isset( $post->editLastId ) || ! absint( $post->editLastId ) ) {
							return null;
						}

						// @codingStandardsIgnoreLine.
						return DataSource::resolve_user( $post->editLastId, $context );
					},
				],
				'editLock'          => [
					'type'        => 'EditLock',
					'description' => __( 'If a user has edited the object within the past 15 seconds, this will return the user and the time they last edited. Null if the edit lock doesn\'t exist or is greater than 15 seconds', 'wp-graphql' ),
				],
				'guid'              => [
					'type'        => 'String',
					'description' => __( 'The global unique identifier for this post. This currently matches the value stored in WP_Post->guid and the guid column in the "post_objects" database table.', 'wp-graphql' ),
				],
				'menuOrder'         => [
					'type'        => 'Int',
					'description' => __( 'A field used for ordering posts. This is typically used with nav menu items or for special ordering of hierarchical content types.', 'wp-graphql' ),

				],
				'desiredSlug'       => [
					'type'        => 'String',
					'description' => __( 'The desired slug of the post', 'wp-graphql' ),
				],
				'link'              => [
					'type'        => 'String',
					'description' => __( 'The permalink of the post', 'wp-graphql' ),
				],
				'uri'               => [
					'type'        => 'String',
					'description' => __( 'URI path for the resource', 'wp-graphql' ),
				],
				'isRestricted'      => [
					'type'        => 'Boolean',
					'description' => __( 'Whether the object is restricted from the current viewer', 'wp-graphql' ),
				],
				'featuredImage' => [
					'type'        => 'MediaItem',
					'description' => __( 'The featured image for the object', 'wp-graphql' ),
					'resolve'     => function( Post $post, $args, AppContext $context, ResolveInfo $info ) {
						// @codingStandardsIgnoreLine.
						if ( empty( $post->featuredImageId ) || ! absint( $post->featuredImageId ) ) {
							return null;
						}

						// @codingStandardsIgnoreLine.
						return DataSource::resolve_post_object( $post->featuredImageId, $context );
					},
				],
				'commentCount' => [
					'type'        => 'Int',
					'description' => __( 'The number of comments. Even though WPGraphQL denotes this field as an integer, in WordPress this field should be saved as a numeric string for compatibility.', 'wp-graphql' ),
				]
			]
		] );

	}

}
