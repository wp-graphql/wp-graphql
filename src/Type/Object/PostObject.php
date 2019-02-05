<?php

namespace WPGraphQL\Type;

use WPGraphQL\Data\DataSource;

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
		],
		$single_name . 'Id' => [
			'type'        => [
				'non_null' => 'Int',
			],
			'description' => __( 'The id field matches the WP_Post->ID field.', 'wp-graphql' ),
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
		],
		'author'            => [
			'type'        => 'User',
			'description' => __( "The author field will return a queryable User type matching the post's author.", 'wp-graphql' ),
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
		],
		'editLast'          => [
			'type'        => 'User',
			'description' => __( 'The user that most recently edited the object', 'wp-graphql' ),

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
			// Translators: placeholder is the name of the post_type
			'description' => sprintf( __( 'Terms connected to the %1$s', 'wp-graphql' ), $single_name ),
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
		],
		'isRestricted' => [
			'type' => 'Boolean',
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
