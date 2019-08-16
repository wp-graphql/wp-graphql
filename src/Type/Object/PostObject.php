<?php
/**
 * WPObject - PostObject
 *
 * @package WPGraphQL\Type
 */

namespace WPGraphQL\Type;

use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Model\Post;
use WPGraphQL\Model\Term;

/**
 * Registers a post_type WPObject type to the schema.
 *
 * @param WP_Post_Type $post_type_object Post type.
 */
function register_post_object_types( $post_type_object ) {
	$single_name = $post_type_object->graphql_single_name;

	register_graphql_object_type(
		$single_name,
		[
			/* translators: post object singular name w/ description */
			'description' => sprintf( __( 'The %s type', 'wp-graphql' ), $single_name ),
			'interfaces'  => [ WPObjectType::node_interface() ],
			'fields'      => get_post_object_fields( $post_type_object ),
		]
	);

	if ( post_type_supports( $post_type_object->name, 'comments' ) ) {
		register_graphql_field(
			$post_type_object->graphql_single_name,
			'commentCount',
			[
				'type'        => 'Int',
				'description' => __( 'The number of comments. Even though WPGraphQL denotes this field as an integer, in WordPress this field should be saved as a numeric string for compatibility.', 'wp-graphql' ),
			]
		);
	}

	if ( post_type_supports( $post_type_object->name, 'thumbnail' ) ) {
		register_graphql_field(
			$post_type_object->graphql_single_name,
			'featuredImage',
			[
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
			]
		);

	}

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
 * @param WP_Post_Type $post_type_object Post type.
 */
function get_post_object_fields( $post_type_object ) {
	$single_name = $post_type_object->graphql_single_name;
	$fields      = [
		'id'                => [
			'type'        => [
				'non_null' => 'ID',
			],
			'description' => __( 'The globally unique ID for the object', 'wp-graphql' ),
		],
		$single_name . 'Id' => [
			'type'        => [
				'non_null' => 'Int',
			],
			'description' => __( 'The id field matches the WP_Post->ID field.', 'wp-graphql' ),
			'resolve'     => function ( Post $post, $args, $context, $info ) {
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
		'enclosure'         => [
			'type'        => 'String',
			'description' => __( 'The RSS enclosure for the object', 'wp-graphql' ),
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
			'resolve'     => function ( $source, $args ) {
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

	if ( 'attachment' === $post_type_object->name ) {
		$fields['excerpt']['isDeprecated']      = true;
		$fields['excerpt']['deprecationReason'] = __( 'Use the caption field instead of excerpt', 'wp-graphql' );
		$fields['content']['isDeprecated']      = true;
		$fields['content']['deprecationReason'] = __( 'Use the description field instead of content', 'wp-graphql' );
	}

	return $fields;

}
