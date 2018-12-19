<?php

namespace WPGraphQL\Type;

use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;

function register_post_object_types( $post_type_object ) {

	$single_name = $post_type_object->graphql_single_name;

	register_graphql_object_type( $single_name, [
		'description' => __( sprintf( 'The %s type', $single_name ), 'wp-graphql' ),
		'interfaces'  => [ WPObjectType::node_interface() ],
		'fields'      => get_post_object_fields( $post_type_object ),
	] );

	if ( post_type_supports( $post_type_object->name, 'comments' ) ) {

		register_graphql_field( $post_type_object->graphql_single_name, 'commentCount', [
			'type'        => 'Int',
			'description' => __( 'The number of comments. Even though WPGraphQL denotes this field as an integer, in WordPress this field should be saved as a numeric string for compatibility.', 'wp-graphql' ),
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
				return ! empty( $post->comment_count ) ? absint( $post->comment_count ) : null;
			}
		] );
	}

	if ( post_type_supports( $post_type_object->name, 'thumbnail' ) ) {
		register_graphql_field( $post_type_object->graphql_single_name, 'featuredImage', [
			'type'        => 'MediaItem',
			'description' => __( 'The featured image for the object', 'wp-graphql' ),
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
				$thumbnail_id = get_post_thumbnail_id( $post->ID );

				return ! empty( $thumbnail_id ) ? DataSource::resolve_post_object( $thumbnail_id, 'attachment' ) : null;

			},
		] );
	}

	/**
	 * Register fields to the Type used for attachments (MediaItem)
	 */
	if ( 'attachment' === $post_type_object->name && true === $post_type_object->show_in_graphql && isset( $post_type_object->graphql_single_name ) ) {

		/**
		 * Register fields custom to the MediaItem Type
		 */
		register_graphql_fields( $post_type_object->graphql_single_name, [
			'caption'      => [
				'type'        => 'String',
				'description' => __( 'The caption for the resource', 'wp-graphql' ),
				'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
					$caption = apply_filters( 'the_excerpt', apply_filters( 'get_the_excerpt', $post->post_excerpt, $post ) );

					return ! empty( $caption ) ? $caption : null;
				},
			],
			'altText'      => [
				'type'        => 'String',
				'description' => __( 'Alternative text to display when resource is not displayed', 'wp-graphql' ),
				'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
					return get_post_meta( $post->ID, '_wp_attachment_image_alt', true );
				},
			],
			'description'  => [
				'type'        => 'String',
				'description' => __( 'Description of the image (stored as post_content)', 'wp-graphql' ),
				'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
					return apply_filters( 'the_content', $post->post_content );
				},
			],
			'mediaType'    => [
				'type'        => 'String',
				'description' => __( 'Type of resource', 'wp-graphql' ),
				'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
					return wp_attachment_is_image( $post->ID ) ? 'image' : 'file';
				},
			],
			'sourceUrl'    => [
				'type'        => 'String',
				'description' => __( 'Url of the mediaItem', 'wp-graphql' ),
				'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
					return wp_get_attachment_url( $post->ID );
				},
			],
			'mimeType'     => [
				'type'        => 'String',
				'description' => __( 'The mime type of the mediaItem', 'wp-graphql' ),
				'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
					return ! empty( $post->post_mime_type ) ? $post->post_mime_type : null;
				},
			],
			'mediaDetails' => [
				'type'        => 'MediaDetails',
				'description' => __( 'Details about the mediaItem', 'wp-graphql' ),
				'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
					$media_details = wp_get_attachment_metadata( $post->ID );

					if ( ! empty( $media_details ) ) {
						$media_details['ID'] = $post->ID;

						return $media_details;
					}

					return null;
				},
			],

		] );

	}

}

function get_post_object_fields( $post_type_object ) {

	$single_name = $post_type_object->graphql_single_name;
	$fields      = [
		'id'                => [
			'type'        => [
				'non_null' => 'ID',
			],
			'description' => __( 'The globally unique ID for the object', 'wp-graphql' ),
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
				return ( ! empty( $post->post_type ) && ! empty( $post->ID ) ) ? Relay::toGlobalId( $post->post_type, $post->ID ) : null;
			},
		],
		$single_name . 'Id' => [
			'type'        => [
				'non_null' => 'Int',
			],
			'description' => __( 'The id field matches the WP_Post->ID field.', 'wp-graphql' ),
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
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
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
				$ancestors    = [];
				$types        = ! empty( $args['types'] ) ? $args['types'] : [ $post->post_type ];
				$ancestor_ids = get_ancestors( $post->ID, $post->post_type );
				if ( ! empty( $ancestor_ids ) ) {
					foreach ( $ancestor_ids as $ancestor_id ) {
						$ancestor_obj = get_post( $ancestor_id );
						if ( in_array( $ancestor_obj->post_type, $types, true ) ) {
							$ancestors[] = DataSource::resolve_post_object( $ancestor_obj->ID, $ancestor_obj->post_type );
						}
					}
				}

				return ! empty( $ancestors ) ? $ancestors : null;
			},
		],
		'author'            => [
			'type'        => 'User',
			'description' => __( "The author field will return a queryable User type matching the post's author.", 'wp-graphql' ),
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
				return DataSource::resolve_user( $post->post_author );
			},
		],
		'date'              => [
			'type'        => 'String',
			'description' => __( 'Post publishing date.', 'wp-graphql' ),
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
				return ! empty( $post->post_date ) ? $post->post_date : null;
			},
		],
		'dateGmt'           => [
			'type'        => 'String',
			'description' => __( 'The publishing date set in GMT.', 'wp-graphql' ),
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
				return ! empty( $post->post_date_gmt ) ? Types::prepare_date_response( $post->post_date_gmt ) : null;
			},
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
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {

				$content = ! empty( $post->post_content ) ? $post->post_content : null;

				// If the raw format is requested, don't apply any filters.
				if ( isset( $args['format'] ) && 'raw' === $args['format'] ) {
					return $content;
				}

				return apply_filters( 'the_content', $content );
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
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {

				$id    = ! empty( $post->ID ) ? $post->ID : null;
				$title = ! empty( $post->post_title ) ? $post->post_title : null;

				// If the raw format is requested, don't apply any filters.
				if ( isset( $args['format'] ) && 'raw' === $args['format'] ) {
					return $title;
				}

				return apply_filters( 'the_title', $title, $id );
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
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {

				$excerpt = ! empty( $post->post_excerpt ) ? $post->post_excerpt : null;

				// If the raw format is requested, don't apply any filters.
				if ( isset( $args['format'] ) && 'raw' === $args['format'] ) {
					return $excerpt;
				}

				$excerpt = apply_filters( 'get_the_excerpt', $excerpt, $post );

				return apply_filters( 'the_excerpt', $excerpt );
			},
		],
		'status'            => [
			'type'        => 'String',
			'description' => __( 'The current status of the object', 'wp-graphql' ),
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
				return ! empty( $post->post_status ) ? $post->post_status : null;
			},
		],
		'commentStatus'     => array(
			'type'        => 'String',
			'description' => __( 'Whether the comments are open or closed for this particular post.', 'wp-graphql' ),
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
				return ! empty( $post->comment_status ) ? $post->comment_status : null;
			},
		),
		'pingStatus'        => [
			'type'        => 'String',
			'description' => __( 'Whether the pings are open or closed for this particular post.', 'wp-graphql' ),
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
				return ! empty( $post->ping_status ) ? $post->ping_status : null;
			},
		],
		'slug'              => [
			'type'        => 'String',
			'description' => __( 'The uri slug for the post. This is equivalent to the WP_Post->post_name field and the post_name column in the database for the "post_objects" table.', 'wp-graphql' ),
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
				return ! empty( $post->post_name ) ? $post->post_name : null;
			},
		],
		'toPing'            => [
			'type'        => [ 'list_of' => 'String' ],
			'description' => __( 'URLs queued to be pinged.', 'wp-graphql' ),
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
				return ! empty( $post->to_ping ) ? implode( ',', $post->to_ping ) : null;
			},
		],
		'pinged'            => [
			'type'        => [ 'list_of' => 'String' ],
			'description' => __( 'URLs that have been pinged.', 'wp-graphql' ),
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
				return ! empty( $post->pinged ) ? implode( ',', $post->pinged ) : null;
			},
		],
		'modified'          => [
			'type'        => 'String',
			'description' => __( 'The local modified time for a post. If a post was recently updated the modified field will change to match the corresponding time.', 'wp-graphql' ),
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
				return ! empty( $post->post_modified ) ? $post->post_modified : null;
			},
		],
		'modifiedGmt'       => [
			'type'        => 'String',
			'description' => __( 'The GMT modified time for a post. If a post was recently updated the modified field will change to match the corresponding time in GMT.', 'wp-graphql' ),
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
				return ! empty( $post->post_modified_gmt ) ? Types::prepare_date_response( $post->post_modified_gmt ) : null;
			},
		],
		'parent'            => [
			'type'        => 'PostObjectUnion',
			'description' => __( 'The parent of the object. The parent object can be of various types', 'wp-graphql' ),
			'resolve'     => function ( \WP_Post $post, array $args, $context, $info ) {
				$parent_post = ! empty( $post->post_parent ) ? get_post( $post->post_parent ) : null;

				return isset( $parent_post->ID ) && isset( $parent_post->post_type ) ? DataSource::resolve_post_object( $parent_post->ID, $parent_post->post_type ) : $parent_post;
			},
		],
		'editLast'          => [
			'type'        => 'User',
			'description' => __( 'The user that most recently edited the object', 'wp-graphql' ),
			'resolve'     => function ( \WP_Post $post, array $args, $context, $info ) {
				$edit_last = get_post_meta( $post->ID, '_edit_last', true );

				return ! empty( $edit_last ) ? DataSource::resolve_user( absint( $edit_last ) ) : null;
			},
		],
		'editLock'          => [
			'type'        => 'EditLock',
			'description' => __( 'If a user has edited the object within the past 15 seconds, this will return the user and the time they last edited. Null if the edit lock doesn\'t exist or is greater than 15 seconds', 'wp-graphql' ),
			'resolve'     => function ( \WP_Post $post, array $args, $context, $info ) {
				$edit_lock       = get_post_meta( $post->ID, '_edit_lock', true );
				$edit_lock_parts = explode( ':', $edit_lock );

				return ! empty( $edit_lock_parts ) ? $edit_lock_parts : null;
			},
		],
		'enclosure'         => [
			'type'        => 'String',
			'description' => __( 'The RSS enclosure for the object', 'wp-graphql' ),
			'resolve'     => function ( \WP_Post $post, array $args, $context, $info ) {
				$enclosure = get_post_meta( $post->ID, 'enclosure', true );

				return ! empty( $enclosure ) ? $enclosure : null;
			},
		],
		'guid'              => [
			'type'        => 'String',
			'description' => __( 'The global unique identifier for this post. This currently matches the value stored in WP_Post->guid and the guid column in the "post_objects" database table.', 'wp-graphql' ),
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
				return ! empty( $post->guid ) ? $post->guid : null;
			},
		],
		'menuOrder'         => [
			'type'        => 'Int',
			'description' => __( 'A field used for ordering posts. This is typically used with nav menu items or for special ordering of hierarchical content types.', 'wp-graphql' ),
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
				return ! empty( $post->menu_order ) ? absint( $post->menu_order ) : null;
			},
		],
		'desiredSlug'       => [
			'type'        => 'String',
			'description' => __( 'The desired slug of the post', 'wp-graphql' ),
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
				$desired_slug = get_post_meta( $post->ID, '_wp_desired_post_slug', true );

				return ! empty( $desired_slug ) ? $desired_slug : null;
			},
		],
		'link'              => [
			'type'        => 'String',
			'description' => __( 'The permalink of the post', 'wp-graphql' ),
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
				$link = get_permalink( $post->ID );

				return ! empty( $link ) ? $link : null;
			},
		],
		'uri'               => [
			'type'        => 'String',
			'description' => __( 'URI path for the resource', 'wp-graphql' ),
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {
				$uri = get_page_uri( $post->ID );

				return ! empty( $uri ) ? $uri : null;
			},
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
			// Translators: placeholder is the name of the post_type
			'description' => sprintf( __( 'Terms connected to the %1$s', 'wp-graphql' ), $single_name ),
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {

				/**
				 * If the $arg for taxonomies is populated, use it as the $allowed_taxonomies
				 * otherwise use the default $allowed_taxonomies passed down
				 */
				$taxonomies = [];
				if ( ! empty( $args['taxonomies'] ) && is_array( $args['taxonomies'] ) ) {
					$taxonomies = $args['taxonomies'];
				} else {
					$connected_taxonomies = get_object_taxonomies( $post, 'names' );
					foreach ( $connected_taxonomies as $taxonomy ) {
						if ( in_array( $taxonomy, \WPGraphQL::$allowed_taxonomies ) ) {
							$taxonomies[] = $taxonomy;
						}
					}
				}

				$tax_terms = [];
				if ( ! empty( $taxonomies ) ) {

					$term_query = new \WP_Term_Query( [
						'taxonomy'   => $taxonomies,
						'object_ids' => $post->ID,
					] );

					$tax_terms = $term_query->get_terms();

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
			// Translators: placeholder is the name of the post_type
			'description' => sprintf( __( 'Terms connected to the %1$s', 'wp-graphql' ), $single_name ),
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {

				/**
				 * If the $arg for taxonomies is populated, use it as the $allowed_taxonomies
				 * otherwise use the default $allowed_taxonomies passed down
				 */
				$taxonomies = [];
				if ( ! empty( $args['taxonomies'] ) && is_array( $args['taxonomies'] ) ) {
					$taxonomies = $args['taxonomies'];
				} else {
					$connected_taxonomies = get_object_taxonomies( $post, 'names' );
					foreach ( $connected_taxonomies as $taxonomy ) {
						if ( in_array( $taxonomy, \WPGraphQL::$allowed_taxonomies ) ) {
							$taxonomies[] = $taxonomy;
						}
					}
				}

				$tax_terms = [];
				if ( ! empty( $taxonomies ) ) {

					$term_query = new \WP_Term_Query( [
						'taxonomy'   => $taxonomies,
						'object_ids' => [ $post->ID ],
					] );

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
			// Translators: placeholder is the name of the post_type
			'description' => sprintf( __( 'Terms connected to the %1$s', 'wp-graphql' ), $single_name ),
			'resolve'     => function ( \WP_Post $post, $args, $context, $info ) {

				/**
				 * If the $arg for taxonomies is populated, use it as the $allowed_taxonomies
				 * otherwise use the default $allowed_taxonomies passed down
				 */
				$taxonomies = [];
				if ( ! empty( $args['taxonomies'] ) && is_array( $args['taxonomies'] ) ) {
					$taxonomies = $args['taxonomies'];
				} else {
					$connected_taxonomies = get_object_taxonomies( $post, 'names' );
					foreach ( $connected_taxonomies as $taxonomy ) {
						if ( in_array( $taxonomy, \WPGraphQL::$allowed_taxonomies ) ) {
							$taxonomies[] = $taxonomy;
						}
					}
				}

				$tax_terms = [];
				if ( ! empty( $taxonomies ) ) {

					$term_query = new \WP_Term_Query( [
						'taxonomy'   => $taxonomies,
						'object_ids' => [ $post->ID ],
					] );

					$tax_terms = $term_query->get_terms();

				}
				$term_slugs = ! empty( $tax_terms ) && is_array( $tax_terms ) ? wp_list_pluck( $tax_terms, 'slug' ) : [];

				return ! empty( $term_slugs ) ? $term_slugs : null;
			},
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
