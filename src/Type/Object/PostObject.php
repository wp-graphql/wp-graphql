<?php

namespace WPGraphQL\Type\Object;

use WP_Post_Type;
use WPGraphQL\Model\Post;
use WPGraphQL\Registry\TypeRegistry;

/**
 * WPObject - PostObject
 *
 * @package WPGraphQL\Type
 */
class PostObject {

	/**
	 * Registers a post_type WPObject type to the schema.
	 *
	 * @param WP_Post_Type $post_type_object Post type.
	 * @param TypeRegistry $type_registry    The Type Registry
	 *
	 * @return void
	 */
	public static function register_post_object_types( WP_Post_Type $post_type_object, TypeRegistry $type_registry ) {

		$single_name = $post_type_object->graphql_single_name;

		$interfaces = [ 'Node', 'ContentNode', 'DatabaseIdentifier', 'NodeWithTemplate' ];

		if ( true === $post_type_object->public ) {
			$interfaces[] = 'UniformResourceIdentifiable';
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

		register_graphql_object_type(
			$single_name,
			[
				/* translators: post object singular name w/ description */
				'description' => sprintf( __( 'The %s type', 'wp-graphql' ), $single_name ),
				'interfaces'  => $interfaces,
				'fields'      => self::get_post_object_fields( $post_type_object, $type_registry ),
			]
		);

		/**
		 * Register fields to the Type used for attachments (MediaItem)
		 */
		if ( 'attachment' === $post_type_object->name && true === $post_type_object->show_in_graphql && isset( $post_type_object->graphql_single_name ) ) {

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
						'resolve'     => function( $source, $args ) {
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
						'resolve'     => function( $source, $args ) {
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
						'resolve'     => function( $source, $args ) {
							$size = 'medium';
							if ( ! empty( $args['size'] ) ) {
								$size = $args['size'];
							}

							$url = wp_get_attachment_image_src( $source->ID, $size );
							if ( ! is_array( $url ) || ! isset( $url[0] ) ) {
								return null;
							}

							$sizes = wp_calculate_image_sizes( $size, $url[0], null, $source->ID );

							return ! empty( $sizes ) ? $sizes : null;
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
						'resolve'     => function( $source, $args ) {
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
						'resolve'     => function( $image, $args, $context, $info ) {
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
						'resolve'     => function( $image, $args, $context, $info ) {

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

	/**
	 * Registers common post type fields on schema type corresponding to provided post type object.
	 *
	 * @param WP_Post_Type $post_type_object Post type.
	 * @param TypeRegistry $type_registry    The Type Registry
	 *
	 * @return array
	 */
	public static function get_post_object_fields( $post_type_object, $type_registry ) {
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
				'resolve'           => function( Post $post, $args, $context, $info ) {
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
			$fields['ancestors']['deprecationReason'] = __( 'This content type is not hierarchical and typcially will not have ancestors', 'wp-graphql' );
			$fields['parent']['deprecationReason']    = __( 'This content type is not hierarchical and typcially will not have a parent', 'wp-graphql' );
		}

		return $fields;

	}
}


