<?php

namespace WPGraphQL\Registry\Utils;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL;
use WPGraphQL\AppContext;
use WPGraphQL\Data\Connection\CommentConnectionResolver;
use WPGraphQL\Data\Connection\PostObjectConnectionResolver;
use WPGraphQL\Data\Connection\TermObjectConnectionResolver;
use WPGraphQL\Model\Post;
use WPGraphQL\Type\Connection\Comments;
use WPGraphQL\Type\Connection\PostObjects;
use WPGraphQL\Type\Connection\TermObjects;
use WP_Post_Type;

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
	 * @param \WP_Post_Type $post_type_object Post type.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function register_types( WP_Post_Type $post_type_object ) {
		$single_name = $post_type_object->graphql_single_name;

		$config = [
			'description' => static function () use ( $post_type_object, $single_name ) {
				return ! empty( $post_type_object->graphql_description )
					? $post_type_object->graphql_description
					: ( ! empty( $post_type_object->description )
						? $post_type_object->description
					/* translators: post object singular name w/ description */
						: sprintf( __( 'The %s type', 'wp-graphql' ), $single_name )
					);
			},
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
					// translators: %1$s is the post type name, %2$s is the graphql kind.
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
	 * @param \WP_Post_Type $post_type_object
	 *
	 * @return array<string,array<string,mixed>>
	 */
	protected static function get_connections( WP_Post_Type $post_type_object ) {
		$connections = [];

		// Comments.
		if ( post_type_supports( $post_type_object->name, 'comments' ) ) {
			$connections['comments'] = [
				'toType'         => 'Comment',
				'connectionArgs' => Comments::get_connection_args(),
				'resolve'        => static function ( Post $post, $args, $context, $info ) {
					if ( $post->isRevision ) {
						$id = $post->parentDatabaseId;
					} else {
						$id = $post->databaseId;
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
				'deprecationReason'  => ( true === $post_type_object->publicly_queryable || true === $post_type_object->public ) ? null
					: sprintf(
						// translators: %s is the post type's GraphQL name.
						__( 'The "%s" Type is not publicly queryable and does not support previews. This field will be removed in the future.', 'wp-graphql' ),
						WPGraphQL\Utils\Utils::format_type_name( $post_type_object->graphql_single_name )
					),
				'resolve'            => static function ( Post $post, $args, AppContext $context, ResolveInfo $info ) {
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
				'resolve'            => static function ( Post $post, $args, $context, $info ) {
					$resolver = new PostObjectConnectionResolver( $post, $args, $context, $info, 'revision' );
					$resolver->set_query_arg( 'post_parent', $post->databaseId );

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
								'description' => static function () {
									return __( 'The Taxonomy to filter terms by', 'wp-graphql' );
								},
							],
						]
					),
					'resolve'        => static function ( Post $post, $args, AppContext $context, ResolveInfo $info ) {
						$taxonomies = \WPGraphQL::get_allowed_taxonomies();
						$object_id  = true === $post->isPreview && ! empty( $post->parentDatabaseId ) ? $post->parentDatabaseId : $post->databaseId;

						if ( empty( $object_id ) ) {
							return null;
						}

						$resolver = new TermObjectConnectionResolver( $post, $args, $context, $info, $taxonomies );
						$resolver->set_query_arg( 'object_ids', absint( $object_id ) );
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
				'resolve'        => static function ( Post $post, $args, AppContext $context, $info ) use ( $tax_object ) {
					$object_id = true === $post->isPreview && ! empty( $post->parentDatabaseId ) ? $post->parentDatabaseId : $post->databaseId;

					if ( empty( $object_id ) || ! absint( $object_id ) ) {
						return null;
					}

					$resolver = new TermObjectConnectionResolver( $post, $args, $context, $info, $tax_object->name );
					$resolver->set_query_arg( 'object_ids', absint( $object_id ) );

					return $resolver->get_connection();
				},
			];
		}

		// Deprecated connections.
		if ( ! $post_type_object->hierarchical &&
			! in_array(
				$post_type_object->name,
				[
					'attachment',
					'revision',
				],
				true
			) ) {
			$connections['ancestors'] = [
				'toType'            => $post_type_object->graphql_single_name,
				'description'       => static function () {
					return __( 'The ancestors of the content node.', 'wp-graphql' );
				},
				'deprecationReason' => static function () {
					return __( 'This content type is not hierarchical and typically will not have ancestors', 'wp-graphql' );
				},
				'resolve'           => static function () {
					return null;
				},
			];
			$connections['parent']    = [
				'toType'            => $post_type_object->graphql_single_name,
				'oneToOne'          => true,
				'description'       => static function () {
					return __( 'The parent of the content node.', 'wp-graphql' );
				},
				'deprecationReason' => static function () {
					return __( 'This content type is not hierarchical and typically will not have a parent', 'wp-graphql' );
				},
				'resolve'           => static function () {
					return null;
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
	 * @param \WP_Post_Type $post_type_object Post type.
	 *
	 * @return string[]
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
	 * @param \WP_Post_Type $post_type_object Post type.
	 *
	 * @return array<string,array<string,mixed>>
	 * @todo make protected after \Type\ObjectType\PostObject::get_fields() is removed.
	 */
	public static function get_fields( WP_Post_Type $post_type_object ) {
		$single_name = $post_type_object->graphql_single_name;
		$fields      = [
			'id'                => [
				'description' => static function () use ( $post_type_object ) {
					return sprintf(
						/* translators: %s: custom post-type name */
						__( 'The globally unique identifier of the %s object.', 'wp-graphql' ),
						$post_type_object->name
					);
				},
			],
			$single_name . 'Id' => [
				'type'              => [
					'non_null' => 'Int',
				],
				'deprecationReason' => static function () {
					return __( 'Deprecated in favor of the databaseId field', 'wp-graphql' );
				},
				'description'       => static function () {
					return __( 'The id field matches the WP_Post->ID field.', 'wp-graphql' );
				},
				'resolve'           => static function ( Post $post ) {
					return absint( $post->databaseId );
				},
			],
			'hasPassword'       => [
				'type'        => 'Boolean',
				'description' => static function () use ( $post_type_object ) {
					return sprintf(
						// translators: %s: custom post-type name.
						__( 'Whether the %s object is password protected.', 'wp-graphql' ),
						$post_type_object->name
					);
				},
			],
			'password'          => [
				'type'        => 'String',
				'description' => static function () use ( $post_type_object ) {
					return sprintf(
						// translators: %s: custom post-type name.
						__( 'The password for the %s object.', 'wp-graphql' ),
						$post_type_object->name
					);
				},
			],
		];

		if ( 'page' === $post_type_object->name ) {
			$fields['isFrontPage'] = [
				'type'        => [ 'non_null' => 'Bool' ],
				'description' => static function () {
					return __( 'Whether this page is set to the static front page.', 'wp-graphql' );
				},
			];

			$fields['isPostsPage'] = [
				'type'        => [ 'non_null' => 'Bool' ],
				'description' => static function () {
					return __( 'Whether this page is set to the blog posts page.', 'wp-graphql' );
				},
			];

			$fields['isPrivacyPage'] = [
				'type'        => [ 'non_null' => 'Bool' ],
				'description' => static function () {
					return __( 'Whether this page is set to the privacy page.', 'wp-graphql' );
				},
			];
		}

		if ( 'post' === $post_type_object->name ) {
			$fields['isSticky'] = [
				'type'        => [ 'non_null' => 'Bool' ],
				'description' => static function () {
					return __( 'Whether this page is sticky', 'wp-graphql' );
				},
			];
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
	 * @param \WP_Post_Type $post_type_object Post type.
	 */
	private static function register_attachment_fields( WP_Post_Type $post_type_object ): void {
		/**
		 * Register fields custom to the MediaItem Type
		 */
		register_graphql_fields(
			$post_type_object->graphql_single_name,
			[
				'caption'      => [
					'type'        => 'String',
					'description' => static function () {
						return __( 'The caption for the resource', 'wp-graphql' );
					},
					'args'        => [
						'format' => [
							'type'        => 'PostObjectFieldFormatEnum',
							'description' => static function () {
								return __( 'Format of the field output', 'wp-graphql' );
							},
						],
					],
					'resolve'     => static function ( $source, $args ) {
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
					'description' => static function () {
						return __( 'Alternative text to display when resource is not displayed', 'wp-graphql' );
					},
				],
				'srcSet'       => [
					'type'        => 'string',
					'args'        => [
						'size' => [
							'type'        => 'MediaItemSizeEnum',
							'description' => static function () {
								return __( 'Size of the MediaItem to calculate srcSet with', 'wp-graphql' );
							},
						],
					],
					'description' => static function () {
						return __( 'The srcset attribute specifies the URL of the image to use in different situations. It is a comma separated string of urls and their widths.', 'wp-graphql' );
					},
					'resolve'     => static function ( $source, $args ) {
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
							'description' => static function () {
								return __( 'Size of the MediaItem to calculate sizes with', 'wp-graphql' );
							},
						],
					],
					'description' => static function () {
						return __( 'The sizes attribute value for an image.', 'wp-graphql' );
					},
					'resolve'     => static function ( $source, $args ) {
						$size = 'medium';
						if ( ! empty( $args['size'] ) ) {
							$size = $args['size'];
						}

						$image = wp_get_attachment_image_src( $source->ID, $size );
						if ( $image ) {
							list( $src, $width, $height ) = $image;
							$sizes                        = wp_calculate_image_sizes(
								[
									absint( $width ),
									absint( $height ),
								],
								$src,
								null,
								$source->ID
							);

							return ! empty( $sizes ) ? $sizes : null;
						}

						return null;
					},
				],
				'description'  => [
					'type'        => 'String',
					'description' => static function () {
						return __( 'Description of the image (stored as post_content)', 'wp-graphql' );
					},
					'args'        => [
						'format' => [
							'type'        => 'PostObjectFieldFormatEnum',
							'description' => static function () {
								return __( 'Format of the field output', 'wp-graphql' );
							},
						],
					],
					'resolve'     => static function ( $source, $args ) {
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
					'description' => static function () {
						return __( 'Url of the mediaItem', 'wp-graphql' );
					},
				],
				'mediaType'    => [
					'type'        => 'String',
					'description' => static function () {
						return __( 'Type of resource', 'wp-graphql' );
					},
				],
				'sourceUrl'    => [
					'type'        => 'String',
					'description' => static function () {
						return __( 'Url of the mediaItem', 'wp-graphql' );
					},
					'args'        => [
						'size' => [
							'type'        => 'MediaItemSizeEnum',
							'description' => static function () {
								return __( 'Size of the MediaItem to return', 'wp-graphql' );
							},
						],
					],
					'resolve'     => static function ( $image, $args ) {
						if ( empty( $args['size'] ) ) {
							return $image->sourceUrl;
						}

						// @todo why do we coerce full to large?
						$size = 'full' === $args['size'] ? 'large' : $args['size'];

						/** @var \WPGraphQL\Model\Post $image */
						return $image->get_source_url_by_size( $size );
					},
				],
				'file'         => [
					'type'        => 'String',
					'description' => static function () {
						return __( 'The filename of the mediaItem for the specified size (default size is full)', 'wp-graphql' );
					},
					'args'        => [
						'size' => [
							'type'        => 'MediaItemSizeEnum',
							'description' => static function () {
								return __( 'Size of the MediaItem to return', 'wp-graphql' );
							},
						],
					],
					'resolve'     => static function ( $source, $args ) {
						// If a size is specified, get the size-specific filename
						if ( ! empty( $args['size'] ) ) {
							$size = 'full' === $args['size'] ? 'large' : $args['size'];

							// Get the metadata which contains size information
							$metadata = wp_get_attachment_metadata( $source->databaseId );

							if ( ! empty( $metadata['sizes'][ $size ]['file'] ) ) {
								return $metadata['sizes'][ $size ]['file'];
							}
						}

						// Default to original file
						$attached_file = get_post_meta( $source->databaseId, '_wp_attached_file', true );
						return ! empty( $attached_file ) ? basename( $attached_file ) : null;
					},
				],
				'filePath'     => [
					'type'        => 'String',
					'description' => static function () {
						return __( 'The path to the original file relative to the uploads directory', 'wp-graphql' );
					},
					'args'        => [
						'size' => [
							'type'        => 'MediaItemSizeEnum',
							'description' => static function () {
								return __( 'Size of the MediaItem to return', 'wp-graphql' );
							},
						],
					],
					'resolve'     => static function ( $source, $args ) {
						// Get the upload directory info
						$upload_dir           = wp_upload_dir();
						$relative_upload_path = wp_make_link_relative( $upload_dir['baseurl'] );

						// If a size is specified, get the size-specific path
						if ( ! empty( $args['size'] ) ) {
							$size = 'full' === $args['size'] ? 'large' : $args['size'];

							// Get the metadata which contains size information
							$metadata = wp_get_attachment_metadata( $source->databaseId );

							if ( ! empty( $metadata['sizes'][ $size ]['file'] ) ) {
								$file_path = $metadata['file'];
								return path_join( $relative_upload_path, dirname( $file_path ) . '/' . $metadata['sizes'][ $size ]['file'] );
							}
						}

						// Default to original file path
						$attached_file = get_post_meta( $source->databaseId, '_wp_attached_file', true );

						if ( empty( $attached_file ) ) {
							return null;
						}

						return path_join( $relative_upload_path, $attached_file );
					},
				],
				'fileSize'     => [
					'type'        => 'Int',
					'description' => static function () {
						return __( 'The filesize in bytes of the resource', 'wp-graphql' );
					},
					'args'        => [
						'size' => [
							'type'        => 'MediaItemSizeEnum',
							'description' => static function () {
								return __( 'Size of the MediaItem to return', 'wp-graphql' );
							},
						],
					],
					'resolve'     => static function ( $image, $args ) {
						/**
						 * By default, use the mediaItemUrl.
						 *
						 * @var \WPGraphQL\Model\Post $image
						 */
						$source_url = $image->mediaItemUrl;

						// If there's a url for the provided size, use that instead.
						if ( ! empty( $args['size'] ) ) {
							$size = ( 'full' === $args['size'] ) ? 'large' : $args['size'];

							$source_url = $image->get_source_url_by_size( $size ) ?: $source_url;
						}

						// If there's no source_url, return null.
						if ( empty( $source_url ) ) {
							return null;
						}

						$path_parts    = pathinfo( $source_url );
						$original_file = get_attached_file( absint( $image->databaseId ) );
						$filesize_path = ! empty( $original_file ) ? path_join( dirname( $original_file ), $path_parts['basename'] ) : null;

						return ! empty( $filesize_path ) ? filesize( $filesize_path ) : null;
					},
				],
				'mimeType'     => [
					'type'        => 'String',
					'description' => static function () {
						return __( 'The mime type of the mediaItem', 'wp-graphql' );
					},
				],
				'mediaDetails' => [
					'type'        => 'MediaDetails',
					'description' => static function () {
						return __( 'Details about the mediaItem', 'wp-graphql' );
					},
				],
			]
		);
	}
}
