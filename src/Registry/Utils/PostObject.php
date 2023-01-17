<?php

namespace WPGraphQL\Registry\Utils;

use Exception;
use GraphQL\Type\Definition\ResolveInfo;
use WP_Post_Type;
use WPGraphQL;
use WPGraphQL\AppContext;
use WPGraphQL\Data\Connection\CommentConnectionResolver;
use WPGraphQL\Data\Connection\PostObjectConnectionResolver;
use WPGraphQL\Data\Connection\TermObjectConnectionResolver;
use WPGraphQL\Model\Post;
use WPGraphQL\Type\Connection\Comments;
use WPGraphQL\Type\Connection\PostObjects;
use WPGraphQL\Type\Connection\TermObjects;

/**
 * Class PostObject
 *
 * @package WPGraphQL\Data
 * @since   1.12.0
 */
class PostObject {

	/**
	 * Registers a post_type type to the schema as either a GraphQL object, interface, or union.
	 *
	 * @param WP_Post_Type $post_type_object Post type.
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_types( WP_Post_Type $post_type_object ) {
		$single_name = $post_type_object->graphql_single_name;

		$config = [
			/* translators: post object singular name w/ description */
			'description' => sprintf( __( 'The %s type', 'wp-graphql' ), $single_name ),
			'connections' => static::get_connections( $post_type_object ),
			'interfaces'  => static::get_interfaces( $post_type_object ),
			'fields'      => static::get_fields( $post_type_object ),
			'model'       => Post::class,
		];

		// Register as GraphQL objects.
		if ( 'object' === $post_type_object->graphql_kind ) {
			register_graphql_object_type( $single_name, $config );

			// Register fields to the Type used for attachments (MediaItem)
			if ( 'attachment' === $post_type_object->name && true === $post_type_object->show_in_graphql && isset( $post_type_object->graphql_single_name ) ) {
				self::register_attachment_fields( $post_type_object );
			}

			return;
		}

		/**
		 * Register as GraphQL interfaces or unions.
		 *
		 * It's assumed that the types used in `resolveType` have already been registered to the schema.
		 */

		// Bail early if graphql_resolve_type isnt a vallable callback.
		if ( empty( $post_type_object->graphql_resolve_type ) || ! is_callable( $post_type_object->graphql_resolve_type ) ) {
			graphql_debug(
				sprintf(
					__( '%1$s is registered as a GraphQL %2$s, but has no way to resolve the type. Ensure "graphql_resolve_type" is a valid callback function', 'wp-graphql' ),
					$single_name,
					$post_type_object->graphql_kind
				),
				[ 'registered_post_type_object' => $post_type_object ]
			);

			return;
		}

		$config['resolveType'] = $post_type_object->graphql_resolve_type;

		if ( 'interface' === $post_type_object->graphql_kind ) {
			register_graphql_interface_type( $single_name, $config );

			return;
		} elseif ( 'union' === $post_type_object->graphql_kind ) {

			// Bail early if graphql_union_types is not defined.
			if ( empty( $post_type_object->graphql_union_types ) || ! is_array( $post_type_object->graphql_union_types ) ) {
				graphql_debug(
					__( 'Registering a post type with "graphql_kind" => "union" requires "graphql_union_types" to be a valid array of possible GraphQL type names.', 'wp-graphql' ),
					[ 'registered_post_type_object' => $post_type_object ]
				);

				return;
			}

			// Set the possible types for the union.
			$config['typeNames'] = $post_type_object->graphql_union_types;

			register_graphql_union_type( $single_name, $config );
		}
	}

	/**
	 * Gets all the connections for the given post type.
	 *
	 * @param WP_Post_Type $post_type_object
	 *
	 * @return array
	 */
	protected static function get_connections( WP_Post_Type $post_type_object ) {
		$connections = [];

		// Comments.
		if ( post_type_supports( $post_type_object->name, 'comments' ) ) {
			$connections['comments'] = [
				'toType'         => 'Comment',
				'connectionArgs' => Comments::get_connection_args(),
				'resolve'        => function ( Post $post, $args, $context, $info ) {

					if ( $post->isRevision ) {
						$id = $post->parentDatabaseId;
					} else {
						$id = $post->ID;
					}

					$resolver = new CommentConnectionResolver( $post, $args, $context, $info );

					return $resolver->set_query_arg( 'post_id', absint( $id ) )->get_connection();
				},
			];
		}

		// Previews.
		if ( ! in_array( $post_type_object->name, [ 'attachment', 'revision' ], true ) ) {
			$connections['preview'] = [
				'toType'             => $post_type_object->graphql_single_name,
				'connectionTypeName' => ucfirst( $post_type_object->graphql_single_name ) . 'ToPreviewConnection',
				'oneToOne'           => true,
				'deprecationReason'  => ( true === $post_type_object->publicly_queryable || true === $post_type_object->public ) ? null : sprintf( __( 'The "%s" Type is not publicly queryable and does not support previews. This field will be removed in the future.', 'wp-graphql' ), WPGraphQL\Utils\Utils::format_type_name( $post_type_object->graphql_single_name ) ),
				'resolve'            => function ( Post $post, $args, AppContext $context, ResolveInfo $info ) {
					if ( $post->isRevision ) {
						return null;
					}

					if ( empty( $post->previewRevisionDatabaseId ) ) {
						return null;
					}

					$resolver = new PostObjectConnectionResolver( $post, $args, $context, $info, 'revision' );
					$resolver->set_query_arg( 'p', $post->previewRevisionDatabaseId );

					return $resolver->one_to_one()->get_connection();
				},
			];
		}

		// Revisions.
		if ( true === post_type_supports( $post_type_object->name, 'revisions' ) ) {
			$connections['revisions'] = [
				'connectionTypeName' => ucfirst( $post_type_object->graphql_single_name ) . 'ToRevisionConnection',
				'toType'             => $post_type_object->graphql_single_name,
				'queryClass'         => 'WP_Query',
				'connectionArgs'     => PostObjects::get_connection_args( [], $post_type_object ),
				'resolve'            => function ( Post $post, $args, $context, $info ) {
					$resolver = new PostObjectConnectionResolver( $post, $args, $context, $info, 'revision' );
					$resolver->set_query_arg( 'post_parent', $post->ID );

					return $resolver->get_connection();
				},
			];
		}

		// Used to ensure TermNode connection doesn't get registered multiple times.
		$already_registered = false;
		$allowed_taxonomies = WPGraphQL::get_allowed_taxonomies( 'objects' );

		foreach ( $allowed_taxonomies as $tax_object ) {

			if ( ! in_array( $post_type_object->name, $tax_object->object_type, true ) ) {
				continue;
			}

			// TermNode.
			if ( ! $already_registered ) {
				$connections['terms'] = [
					'toType'         => 'TermNode',
					'queryClass'     => 'WP_Term_Query',
					'connectionArgs' => TermObjects::get_connection_args(
						[
							'taxonomies' => [
								'type'        => [ 'list_of' => 'TaxonomyEnum' ],
								'description' => __( 'The Taxonomy to filter terms by', 'wp-graphql' ),
							],
						]
					),
					'resolve'        => function ( Post $post, $args, AppContext $context, ResolveInfo $info ) {
						$taxonomies = \WPGraphQL::get_allowed_taxonomies();
						$terms      = wp_get_post_terms( $post->ID, $taxonomies, [ 'fields' => 'ids' ] );

						if ( empty( $terms ) || is_wp_error( $terms ) ) {
							return null;
						}
						$resolver = new TermObjectConnectionResolver( $post, $args, $context, $info, $taxonomies );
						$resolver->set_query_arg( 'include', $terms );

						return $resolver->get_connection();
					},
				];

				// We won't need to register this connection again.
				$already_registered = true;
			}

			// TermObjects.
			$connections[ $tax_object->graphql_plural_name ] = [
				'toType'         => $tax_object->graphql_single_name,
				'queryClass'     => 'WP_Term_Query',
				'connectionArgs' => TermObjects::get_connection_args(),
				'resolve'        => function ( Post $post, $args, AppContext $context, $info ) use ( $tax_object ) {

					$object_id = true === $post->isPreview && ! empty( $post->parentDatabaseId ) ? $post->parentDatabaseId : $post->ID;

					if ( empty( $object_id ) || ! absint( $object_id ) ) {
						return null;
					}

					$resolver = new TermObjectConnectionResolver( $post, $args, $context, $info, $tax_object->name );
					$resolver->set_query_arg( 'object_ids', absint( $object_id ) );

					return $resolver->get_connection();
				},
			];

		}

		// Merge with connections set in register_post_type.
		if ( ! empty( $post_type_object->graphql_connections ) ) {
			$connections = array_merge( $connections, $post_type_object->graphql_connections );
		}

		// Remove excluded connections.
		if ( ! empty( $post_type_object->graphql_exclude_connections ) ) {
			foreach ( $post_type_object->graphql_exclude_connections as $connection_name ) {
				unset( $connections[ lcfirst( $connection_name ) ] );
			}
		}

		return $connections;
	}

	/**
	 * Gets all the interfaces for the given post type.
	 *
	 * @param WP_Post_Type $post_type_object Post type.
	 *
	 * @return array
	 */
	protected static function get_interfaces( WP_Post_Type $post_type_object ) {
		$interfaces = [ 'Node', 'ContentNode', 'DatabaseIdentifier', 'NodeWithTemplate' ];

		if ( true === $post_type_object->public ) {
			$interfaces[] = 'UniformResourceIdentifiable';
		}

		// Only post types that are publicly_queryable are previewable
		if ( 'attachment' !== $post_type_object->name && ( true === $post_type_object->publicly_queryable || true === $post_type_object->public ) ) {
			$interfaces[] = 'Previewable';
		}

		if ( post_type_supports( $post_type_object->name, 'title' ) ) {
			$interfaces[] = 'NodeWithTitle';
		}

		if ( post_type_supports( $post_type_object->name, 'editor' ) ) {
			$interfaces[] = 'NodeWithContentEditor';
		}

		if ( post_type_supports( $post_type_object->name, 'author' ) ) {
			$interfaces[] = 'NodeWithAuthor';
		}

		if ( post_type_supports( $post_type_object->name, 'thumbnail' ) ) {
			$interfaces[] = 'NodeWithFeaturedImage';
		}

		if ( post_type_supports( $post_type_object->name, 'excerpt' ) ) {
			$interfaces[] = 'NodeWithExcerpt';
		}

		if ( post_type_supports( $post_type_object->name, 'comments' ) ) {
			$interfaces[] = 'NodeWithComments';
		}

		if ( post_type_supports( $post_type_object->name, 'trackbacks' ) ) {
			$interfaces[] = 'NodeWithTrackbacks';
		}

		if ( post_type_supports( $post_type_object->name, 'revisions' ) ) {
			$interfaces[] = 'NodeWithRevisions';
		}

		if ( post_type_supports( $post_type_object->name, 'page-attributes' ) ) {
			$interfaces[] = 'NodeWithPageAttributes';
		}

		if ( $post_type_object->hierarchical || in_array(
			$post_type_object->name,
			[
				'attachment',
				'revision',
			],
			true
		) ) {
			$interfaces[] = 'HierarchicalContentNode';
		}

		if ( true === $post_type_object->show_in_nav_menus ) {
			$interfaces[] = 'MenuItemLinkable';
		}

		// Merge with interfaces set in register_post_type.
		if ( ! empty( $post_type_object->graphql_interfaces ) ) {
			$interfaces = array_merge( $interfaces, $post_type_object->graphql_interfaces );
		}

		// Remove excluded interfaces.
		if ( ! empty( $post_type_object->graphql_exclude_interfaces ) ) {
			$interfaces = array_diff( $interfaces, $post_type_object->graphql_exclude_interfaces );
		}

		return $interfaces;
	}

	/**
	 * Registers common post type fields on schema type corresponding to provided post type object.
	 *
	 * @param WP_Post_Type $post_type_object Post type.
	 *
	 * @return array
	 * @todo make protected after \Type\ObjectType\PostObject::get_fields() is removed.
	 */
	public static function get_fields( WP_Post_Type $post_type_object ) {
		$single_name = $post_type_object->graphql_single_name;
		$fields      = [
			'id'                => [
				'description' => sprintf(
				/* translators: %s: custom post-type name */
					__( 'The globally unique identifier of the %s object.', 'wp-graphql' ),
					$post_type_object->name
				),
			],
			$single_name . 'Id' => [
				'type'              => [
					'non_null' => 'Int',
				],
				'deprecationReason' => __( 'Deprecated in favor of the databaseId field', 'wp-graphql' ),
				'description'       => __( 'The id field matches the WP_Post->ID field.', 'wp-graphql' ),
				'resolve'           => function ( Post $post, $args, $context, $info ) {
					return absint( $post->ID );
				},
			],
		];

		if ( 'page' === $post_type_object->name ) {
			$fields['isFrontPage'] = [
				'type'        => [ 'non_null' => 'Bool' ],
				'description' => __( 'Whether this page is set to the static front page.', 'wp-graphql' ),
			];

			$fields['isPostsPage'] = [
				'type'        => [ 'non_null' => 'Bool' ],
				'description' => __( 'Whether this page is set to the blog posts page.', 'wp-graphql' ),
			];

			$fields['isPrivacyPage'] = [
				'type'        => [ 'non_null' => 'Bool' ],
				'description' => __( 'Whether this page is set to the privacy page.', 'wp-graphql' ),
			];
		}

		if ( 'post' === $post_type_object->name ) {
			$fields['isSticky'] = [
				'type'        => [ 'non_null' => 'Bool' ],
				'description' => __( 'Whether this page is sticky', 'wp-graphql' ),
			];
		}

		if ( ! $post_type_object->hierarchical &&
			! in_array(
				$post_type_object->name,
				[
					'attachment',
					'revision',
				],
				true
			) ) {
			$fields['ancestors']['deprecationReason'] = __( 'This content type is not hierarchical and typically will not have ancestors', 'wp-graphql' );
			$fields['parent']['deprecationReason']    = __( 'This content type is not hierarchical and typically will not have a parent', 'wp-graphql' );
		}

		// Merge with fields set in register_post_type.
		if ( ! empty( $post_type_object->graphql_fields ) ) {
			$fields = array_merge( $fields, $post_type_object->graphql_fields );
		}

		// Remove excluded fields.
		if ( ! empty( $post_type_object->graphql_exclude_fields ) ) {
			foreach ( $post_type_object->graphql_exclude_fields as $field_name ) {
				unset( $fields[ $field_name ] );
			}
		}

		return $fields;
	}


	/**
	 * Register fields to the Type used for attachments (MediaItem).
	 *
	 * @return void
	 */
	private static function register_attachment_fields( WP_Post_Type $post_type_object ) {
		/**
		 * Register fields custom to the MediaItem Type
		 */
		register_graphql_fields(
			$post_type_object->graphql_single_name,
			[
				'caption'      => [
					'type'        => 'String',
					'description' => __( 'The caption for the resource', 'wp-graphql' ),
					'args'        => [
						'format' => [
							'type'        => 'PostObjectFieldFormatEnum',
							'description' => __( 'Format of the field output', 'wp-graphql' ),
						],
					],
					'resolve'     => function ( $source, $args ) {
						if ( isset( $args['format'] ) && 'raw' === $args['format'] ) {
							// @codingStandardsIgnoreLine.
							return $source->captionRaw;
						}

						// @codingStandardsIgnoreLine.
						return $source->captionRendered;
					},
				],
				'altText'      => [
					'type'        => 'String',
					'description' => __( 'Alternative text to display when resource is not displayed', 'wp-graphql' ),
				],
				'srcSet'       => [
					'type'        => 'string',
					'args'        => [
						'size' => [
							'type'        => 'MediaItemSizeEnum',
							'description' => __( 'Size of the MediaItem to calculate srcSet with', 'wp-graphql' ),
						],
					],
					'description' => __( 'The srcset attribute specifies the URL of the image to use in different situations. It is a comma separated string of urls and their widths.', 'wp-graphql' ),
					'resolve'     => function ( $source, $args ) {
						$size = 'medium';
						if ( ! empty( $args['size'] ) ) {
							$size = $args['size'];
						}

						$src_set = wp_get_attachment_image_srcset( $source->ID, $size );

						return ! empty( $src_set ) ? $src_set : null;
					},
				],
				'sizes'        => [
					'type'        => 'string',
					'args'        => [
						'size' => [
							'type'        => 'MediaItemSizeEnum',
							'description' => __( 'Size of the MediaItem to calculate sizes with', 'wp-graphql' ),
						],
					],
					'description' => __( 'The sizes attribute value for an image.', 'wp-graphql' ),
					'resolve'     => function ( $source, $args ) {
						$size = 'medium';
						if ( ! empty( $args['size'] ) ) {
							$size = $args['size'];
						}

						$image = wp_get_attachment_image_src( $source->ID, $size );
						if ( $image ) {
							list( $src, $width, $height ) = $image;
							$sizes                        = wp_calculate_image_sizes( [
								absint( $width ),
								absint( $height ),
							], $src, null, $source->ID );

							return ! empty( $sizes ) ? $sizes : null;
						}

						return null;
					},
				],
				'description'  => [
					'type'        => 'String',
					'description' => __( 'Description of the image (stored as post_content)', 'wp-graphql' ),
					'args'        => [
						'format' => [
							'type'        => 'PostObjectFieldFormatEnum',
							'description' => __( 'Format of the field output', 'wp-graphql' ),
						],
					],
					'resolve'     => function ( $source, $args ) {
						if ( isset( $args['format'] ) && 'raw' === $args['format'] ) {
							// @codingStandardsIgnoreLine.
							return $source->descriptionRaw;
						}

						// @codingStandardsIgnoreLine.
						return $source->descriptionRendered;
					},
				],
				'mediaItemUrl' => [
					'type'        => 'String',
					'description' => __( 'Url of the mediaItem', 'wp-graphql' ),
				],
				'mediaType'    => [
					'type'        => 'String',
					'description' => __( 'Type of resource', 'wp-graphql' ),
				],
				'sourceUrl'    => [
					'type'        => 'String',
					'description' => __( 'Url of the mediaItem', 'wp-graphql' ),
					'args'        => [
						'size' => [
							'type'        => 'MediaItemSizeEnum',
							'description' => __( 'Size of the MediaItem to return', 'wp-graphql' ),
						],
					],
					'resolve'     => function ( $image, $args, $context, $info ) {
						// @codingStandardsIgnoreLine.
						$size = null;
						if ( isset( $args['size'] ) ) {
							$size = ( 'full' === $args['size'] ) ? 'large' : $args['size'];
						}

						return ! empty( $size ) ? $image->sourceUrlsBySize[ $size ] : $image->sourceUrl;
					},
				],
				'fileSize'     => [
					'type'        => 'Int',
					'description' => __( 'The filesize in bytes of the resource', 'wp-graphql' ),
					'args'        => [
						'size' => [
							'type'        => 'MediaItemSizeEnum',
							'description' => __( 'Size of the MediaItem to return', 'wp-graphql' ),
						],
					],
					'resolve'     => function ( $image, $args, $context, $info ) {

						// @codingStandardsIgnoreLine.
						$size = null;
						if ( isset( $args['size'] ) ) {
							$size = ( 'full' === $args['size'] ) ? 'large' : $args['size'];
						}

						$sourceUrl     = ! empty( $size ) ? $image->sourceUrlsBySize[ $size ] : $image->mediaItemUrl;
						$path_parts    = pathinfo( $sourceUrl );
						$original_file = get_attached_file( absint( $image->databaseId ) );
						$filesize_path = ! empty( $original_file ) ? path_join( dirname( $original_file ), $path_parts['basename'] ) : null;

						return ! empty( $filesize_path ) ? filesize( $filesize_path ) : null;

					},
				],
				'mimeType'     => [
					'type'        => 'String',
					'description' => __( 'The mime type of the mediaItem', 'wp-graphql' ),
				],
				'mediaDetails' => [
					'type'        => 'MediaDetails',
					'description' => __( 'Details about the mediaItem', 'wp-graphql' ),
				],
			]
		);
	}

}
