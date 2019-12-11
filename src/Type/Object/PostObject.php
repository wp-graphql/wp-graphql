<?php
/**
 * WPObject - PostObject
 *
 * @package WPGraphQL\Type
 */

namespace WPGraphQL\Type\Object;

use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\Post;
use WPGraphQL\Model\Term;


class PostObject {
	/**
	 * Registers a post_type WPObject type to the schema.
	 *
	 * @param \WP_Post_Type $post_type_object Post type.
	 */
	public static function register_post_object_types( $post_type_object ) {
		$single_name = $post_type_object->graphql_single_name;

		register_graphql_object_type(
			$single_name,
			[
				/* translators: post object singular name w/ description */
				'description' => sprintf( __( 'The %s type', 'wp-graphql' ), $single_name ),
				'interfaces'  => [ 'Node', 'ContentNode' ],
				'fields'      => self::get_post_object_fields( $post_type_object ),
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
							if ( empty( $url[0] ) ) {
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
							return ! empty( $args['size'] ) ? $image->sourceUrlsBySize[ $args['size'] ] : $image->sourceUrl;
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
	 * @param \WP_Post_Type $post_type_object Post type.
	 *
	 * @return array
	 */
	public static function get_post_object_fields( $post_type_object ) {
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
				'type'        => [
					'non_null' => 'Int',
				],
				'isDeprecated' => true,
				'deprecationReason' => __( 'Deprecated in favor of the databaseId field', 'wp-graphql' ),
				'description' => __( 'The id field matches the WP_Post->ID field.', 'wp-graphql' ),
				'resolve'     => function( Post $post, $args, $context, $info ) {
					return absint( $post->ID );
				},
			],
			'ancestors'         => [
				'type'        => [
					'list_of' => 'PostObjectUnion',
				],
				'description' => esc_html__( 'Ancestors of the object', 'wp-graphql' ),
				'args'        => [
					'types' => [
						'type'        => [
							'list_of' => 'PostTypeEnum',
						],
						'description' => __( 'The types of ancestors to check for. Defaults to the same type as the current object', 'wp-graphql' ),
					],
				],
				'resolve'     => function( $source, $args, AppContext $context, ResolveInfo $info ) {
					$ancestor_ids = get_ancestors( $source->ID, $source->post_type );
					if ( empty( $ancestor_ids ) || ! is_array( $ancestor_ids ) ) {
						return null;
					}
					$context->getLoader( 'post_object' )->buffer( $ancestor_ids );

					return new Deferred(
						function() use ( $context, $ancestor_ids ) {
							// @codingStandardsIgnoreLine.
							return $context->getLoader( 'post_object' )->loadMany( $ancestor_ids );
						}
					);
				},
			],
			'parent'            => [
				'type'        => 'PostObjectUnion',
				'description' => __( 'The parent of the object. The parent object can be of various types', 'wp-graphql' ),
				'resolve'     => function( Post $post, $args, AppContext $context, ResolveInfo $info ) {
					// @codingStandardsIgnoreLine.
					if ( ! isset( $post->parentId ) || ! absint( $post->parentId ) ) {
						return null;
					}

					// @codingStandardsIgnoreLine.
					return DataSource::resolve_post_object( $post->parentId, $context );
				},
			],
			'enclosure'         => [
				'type'        => 'String',
				'description' => __( 'The RSS enclosure for the object', 'wp-graphql' ),
			],
			'terms'             => [
				'type'        => [
					'list_of' => 'TermObjectUnion',
				],
				'args'        => [
					'taxonomies' => [
						'type'        => [
							'list_of' => 'TaxonomyEnum',
						],
						'description' => __( 'Select which taxonomies to limit the results to', 'wp-graphql' ),
					],
				],
				/* translators: placeholder is the name of the post_type */
				'description' => sprintf( __( 'Terms connected to the %1$s', 'wp-graphql' ), $single_name ),
				'resolve'     => function( $source, $args ) {
					// @TODO eventually use a loader here to grab the taxonomies and pass them through the term model.
					/**
					 * If the $arg for taxonomies is populated, use it as the $allowed_taxonomies
					 * otherwise use the default $allowed_taxonomies passed down
					 */
					$taxonomies = [];
					if ( ! empty( $args['taxonomies'] ) && is_array( $args['taxonomies'] ) ) {
						$taxonomies = $args['taxonomies'];
					} else {
						$connected_taxonomies = get_object_taxonomies( $source->post_type, 'names' );
						foreach ( $connected_taxonomies as $taxonomy ) {
							if ( in_array( $taxonomy, \WPGraphQL::get_allowed_taxonomies(), true ) ) {
								$taxonomies[] = $taxonomy;
							}
						}
					}

					$tax_terms = [];
					if ( ! empty( $taxonomies ) ) {
						$term_query = new \WP_Term_Query(
							[
								'taxonomy'   => $taxonomies,
								'object_ids' => $source->ID,
							]
						);

						$fetched_terms = $term_query->get_terms();
						$tax_terms     = [];
						if ( ! empty( $fetched_terms ) ) {
							foreach ( $fetched_terms as $tax_term ) {
								$tax_terms[ $tax_term->term_id ] = new Term( $tax_term );
							}
						}
					}

					return ! empty( $tax_terms ) && is_array( $tax_terms ) ? $tax_terms : null;
				},
			],
			'termNames'         => [
				'type'        => [ 'list_of' => 'String' ],
				'args'        => [
					'taxonomies' => [
						'type'        => [
							'list_of' => 'TaxonomyEnum',
						],
						'description' => __( 'Select which taxonomies to limit the results to', 'wp-graphql' ),
					],
				],
				/* translators: placeholder is the name of the post_type */
				'description' => sprintf( __( 'Terms connected to the %1$s', 'wp-graphql' ), $single_name ),
				'resolve'     => function( $source, $args ) {
					/**
					 * If the $arg for taxonomies is populated, use it as the $allowed_taxonomies
					 * otherwise use the default $allowed_taxonomies passed down
					 */
					$taxonomies = [];
					if ( ! empty( $args['taxonomies'] ) && is_array( $args['taxonomies'] ) ) {
						$taxonomies = $args['taxonomies'];
					} else {
						$connected_taxonomies = get_object_taxonomies( $source->post_type, 'names' );
						foreach ( $connected_taxonomies as $taxonomy ) {
							if ( in_array( $taxonomy, \WPGraphQL::get_allowed_taxonomies(), true ) ) {
								$taxonomies[] = $taxonomy;
							}
						}
					}

					$tax_terms = [];
					if ( ! empty( $taxonomies ) ) {
						$term_query = new \WP_Term_Query(
							[
								'taxonomy'   => $taxonomies,
								'object_ids' => [ $source->ID ],
							]
						);

						$tax_terms = $term_query->get_terms();

					}
					$term_names = ! empty( $tax_terms ) && is_array( $tax_terms ) ? wp_list_pluck( $tax_terms, 'name' ) : [];

					return ! empty( $term_names ) ? $term_names : null;
				},
			],
			'termSlugs'         => [
				'type'        => [ 'list_of' => 'String' ],
				'args'        => [
					'taxonomies' => [
						'type'        => [
							'list_of' => 'TaxonomyEnum',
						],
						'description' => __( 'Select which taxonomies to limit the results to', 'wp-graphql' ),
					],
				],
				/* translators: placeholder is the name of the post_type */
				'description' => sprintf( __( 'Terms connected to the %1$s', 'wp-graphql' ), $single_name ),
				'resolve'     => function( $source, $args ) {
					/**
					 * If the $arg for taxonomies is populated, use it as the $allowed_taxonomies
					 * otherwise use the default $allowed_taxonomies passed down
					 */
					$taxonomies = [];
					if ( ! empty( $args['taxonomies'] ) && is_array( $args['taxonomies'] ) ) {
						$taxonomies = $args['taxonomies'];
					} else {
						$connected_taxonomies = get_object_taxonomies( $source->post_type, 'names' );
						foreach ( $connected_taxonomies as $taxonomy ) {
							if ( in_array( $taxonomy, \WPGraphQL::get_allowed_taxonomies(), true ) ) {
								$taxonomies[] = $taxonomy;
							}
						}
					}

					$tax_terms = [];
					if ( ! empty( $taxonomies ) ) {

						$term_query = new \WP_Term_Query(
							[
								'taxonomy'   => $taxonomies,
								'object_ids' => [ $source->ID ],
							]
						);

						$tax_terms = $term_query->get_terms();

					}
					$term_slugs = ! empty( $tax_terms ) && is_array( $tax_terms ) ? wp_list_pluck( $tax_terms, 'slug' ) : [];

					return ! empty( $term_slugs ) ? $term_slugs : null;
				},
			],
			'isRestricted'      => [
				'type'        => 'Boolean',
				'description' => __( 'Whether the object is restricted from the current viewer', 'wp-graphql' ),
			],
		];

		if ( 'page' === $post_type_object->name ) {
			$fields['isFrontPage'] = [
				'type'        => [ 'non_null' => 'Bool' ],
				'description' => __( 'Whether this page is set to the static front page.', 'wp-graphql' ),
				'resolve' => function( Post $page ) {
					return isset( $page->isFrontPage ) ? (bool) $page->isFrontPage : false;
				}
			];
		}

		if ( 'attachment' === $post_type_object->name ) {
			$fields['excerpt']['isDeprecated']      = true;
			$fields['excerpt']['deprecationReason'] = __( 'Use the caption field instead of excerpt', 'wp-graphql' );
			$fields['content']['isDeprecated']      = true;
			$fields['content']['deprecationReason'] = __( 'Use the description field instead of content', 'wp-graphql' );
		}

		/**
		 * title
		 * editor
		 * author
		 * thumbnail
		 * excerpt
		 * trackbacks
		 * custom-fields
		 * comments
		 * revisions
		 * page-attributes
		 * post-formats
		 */
		if ( ! post_type_supports( $post_type_object->name, 'title' ) ) {
			$fields['title']['isDeprecated'] = true;
			$fields['title']['deprecationReason'] = __( 'This content type does not support the title field', 'wp-graphql' );
			$fields['title']['resolve'] = function() {
				return null;
			};
		}

		if ( ! post_type_supports( $post_type_object->name, 'editor' ) ) {
			$fields['content']['isDeprecated'] = true;
			$fields['content']['deprecationReason'] = __( 'This content type does not support the content editor', 'wp-graphql' );
			$fields['content']['resolve'] = function() {
				return null;
			};
		}

		if ( ! post_type_supports( $post_type_object->name, 'author' ) ) {
			$fields['author']['isDeprecated'] = true;
			$fields['author']['deprecationReason'] = __( 'This content type does not support authors', 'wp-graphql' );
			$fields['author']['resolve'] = function() {
				return null;
			};
		}

		if ( ! post_type_supports( $post_type_object->name, 'thumbnail' ) ) {
			$fields['featuredImage']['isDeprecated'] = true;
			$fields['featuredImage']['deprecationReason'] = __( 'This content type does not support featured images', 'wp-graphql' );
			$fields['featuredImage']['resolve'] = function() {
				return null;
			};
		}

		if ( ! post_type_supports( $post_type_object->name, 'excerpt' ) ) {
			$fields['excerpt']['isDeprecated'] = true;
			$fields['excerpt']['deprecationReason'] = __( 'This content type does not support excerpts', 'wp-graphql' );
			$fields['excerpt']['resolve'] = function() {
				return null;
			};
		}

		if ( ! post_type_supports( $post_type_object->name, 'comments' ) ) {
			$fields['commentCount']['isDeprecated'] = true;
			$fields['commentCount']['deprecationReason'] = __( 'This content type does not support comments', 'wp-graphql' );
			$fields['commentCount']['resolve'] = function() {
				return null;
			};
		}


		return $fields;

	}
}
