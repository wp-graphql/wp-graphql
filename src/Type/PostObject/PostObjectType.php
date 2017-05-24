<?php
namespace WPGraphQL\Type\PostObject;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;

use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Type\Comment\Connection\CommentConnectionDefinition;
use WPGraphQL\Type\TermObject\Connection\TermObjectConnectionDefinition;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Types;

/**
 * Class PostObjectType
 *
 * This sets up the base PostObjectType. Custom Post Types that are set to "show_in_graphql" automatically
 * use the PostObjectType and inherit the fields that are defined here. The fields get passed through a
 * filter unique to each type, so each post_type can modify it's type schema via field filters.
 *
 * NOTE: In some cases the shape of a Custom Post Type's schema is so drastically different from the standard
 * PostObjectType shape it might make more sense for the custom post type to register a different type
 * altogether instead of utilizing the PostObjectType.
 *
 * @package WPGraphQL\Type
 * @since   0.0.5
 */
class PostObjectType extends WPObjectType {

	/**
	 * Holds the $fields definition for the PostObjectType
	 *
	 * @var $fields
	 */
	private static $fields;

	/**
	 * Holds the post_type_object
	 *
	 * @var object $post_type_object
	 */
	private static $post_type_object;

	/**
	 * Holds the object definition for media details
	 * @var object $media_details
	 */
	private static $media_details;

	/**
	 * Holds the object definition for media item meta
	 * @var object $media_item_meta
	 */
	private static $media_item_meta;

	/**
	 * Holds the object definition for media sizes
	 * @var object $media_sizes
	 */
	private static $media_sizes;

	/**
	 * PostObjectType constructor.
	 *
	 * @param string $post_type The post_type name
	 *
	 * @since 0.0.5
	 */
	public function __construct( $post_type ) {

		/**
		 * Get the post_type_object from the post_type and store it
		 * for later use
		 *
		 * @since 0.0.5
		 */
		self::$post_type_object = get_post_type_object( $post_type );

		/**
		 * Adjust the mediaItem fields to have a custom shape
		 * @since 0.0.6
		 */
		add_filter( 'graphql_mediaItem_fields', [ $this, 'media_item_fields' ], 10, 1 );

		$config = [
			'name'        => self::$post_type_object->graphql_single_name,
			// translators: the placeholder is the post_type of the object
			'description' => sprintf( __( 'The %s object type', 'wp-graphql' ), self::$post_type_object->graphql_single_name ),
			'fields'      => self::fields( self::$post_type_object ),
			'interfaces'  => [ self::node_interface() ],
		];
		parent::__construct( $config );
	}

	/**
	 * fields
	 * This defines the fields for PostObjectTypes
	 *
	 * @param $post_type_object
	 *
	 * @return \GraphQL\Type\Definition\FieldDefinition|mixed|null
	 * @since 0.0.5
	 */
	private static function fields( $post_type_object ) {

		/**
		 * Get the $single_name out of the post_type_object
		 *
		 * @since 0.0.5
		 */
		$single_name = self::$post_type_object->graphql_single_name;

		/**
		 * If no fields have been defined for this type already,
		 * make sure the $fields var is an empty array
		 *
		 * @since 0.0.5
		 */
		if ( null === self::$fields ) {
			self::$fields = [];
		}

		/**
		 * If the $fields haven't already been defined for this type,
		 * define the fields
		 *
		 * @since 0.0.5
		 */
		if ( empty( self::$fields[ $single_name ] ) ) {

			/**
			 * Get the taxonomies that are allowed in WPGraphQL
			 *
			 * @since 0.0.5
			 */
			$allowed_taxonomies = \WPGraphQL::$allowed_taxonomies;

			/**
			 * Define the fields for the post_type
			 *
			 * @return mixed
			 * @since 0.0.5
			 */
			self::$fields[ $single_name ] = function() use ( $single_name, $post_type_object, $allowed_taxonomies ) {
				$fields = [
					'id'                => [
						'type'    => Types::non_null( Types::id() ),
						'description' => __( 'The globally unique ID for the object', 'wp-graphql' ),
						'resolve' => function( \WP_Post $post, $args, AppContext $context, ResolveInfo $info ) {
							return ( ! empty( $post->post_type ) && ! empty( $post->ID ) ) ? Relay::toGlobalId( $post->post_type, $post->ID ) : null;
						},
					],
					$single_name . 'Id' => [
						'type'        => Types::non_null( Types::int() ),
						'description' => esc_html__( 'The id field matches the WP_Post->ID field.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $post, $args, AppContext $context, ResolveInfo $info ) {
							return absint( $post->ID );
						},
					],
					'author'            => [
						'type'        => Types::user(),
						'description' => esc_html__( "The author field will return a queryable User type matching the post's author.", 'wp-graphql' ),
						'resolve'     => function( \WP_Post $post, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post->post_author ) ? new \WP_User( $post->post_author ) : null;
						},
					],
					'date'              => [
						'type'        => Types::string(),
						'description' => esc_html__( 'Post publishing date.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $post, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post->post_date ) ? $post->post_date : null;
						},
					],
					'dateGmt'           => [
						'type'        => Types::string(),
						'description' => esc_html__( 'The publishing date set in GMT.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $post, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post->post_date_gmt ) ? $post->post_date_gmt : null;
						},
					],
					'content'           => [
						'type'        => Types::string(),
						'description' => esc_html__( 'The content of the post. This is currently just the raw content. An amendment to support rendered content needs to be made.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $post, $args, AppContext $context, ResolveInfo $info ) {
							return apply_filters( 'the_content', $post->post_content );
						},
					],
					'title'             => [
						'type'        => Types::string(),
						'description' => esc_html__( 'The title of the post. This is currently just the raw title. An amendment to support rendered title needs to be made.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $post, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post->post_title ) ? $post->post_title : null;
						},
					],
					'excerpt'           => [
						'type'        => Types::string(),
						'description' => esc_html__( 'The excerpt of the post. This is currently just the raw excerpt. An amendment to support rendered excerpts needs to be made.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $post, $args, AppContext $context, ResolveInfo $info ) {
							$excerpt = apply_filters( 'the_excerpt', apply_filters( 'get_the_excerpt', $post->post_excerpt, $post ) );

							return ! empty( $excerpt ) ? $excerpt : null;
						},
					],
					'status'            => [
						'type'        => Types::string(),
						'description' => esc_html__( 'The current status of the object', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $post, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post->post_status ) ? $post->post_status : null;
						},
					],
					'commentStatus'     => array(
						'type'        => Types::string(),
						'description' => esc_html__( 'Whether the comments are open or closed for this particular post.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $post, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post->comment_status ) ? $post->comment_status : null;
						},
					),
					'pingStatus'        => [
						'type'        => Types::string(),
						'description' => esc_html__( 'Whether the pings are open or closed for this particular post.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $post, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post->ping_status ) ? $post->ping_status : null;
						},
					],
					'slug'              => [
						'type'        => Types::string(),
						'description' => esc_html__( 'The uri slug for the post. This is equivalent to the WP_Post->post_name field and the post_name column in the database for the `post_objects` table.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $post, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post->post_name ) ? $post->post_name : null;
						},
					],
					'toPing'            => [
						'type'        => Types::boolean(),
						'description' => esc_html__( 'URLs queued to be pinged.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $post, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post->to_ping ) ? true : false;
						},
					],
					'pinged'            => [
						'type'        => Types::boolean(),
						'description' => esc_html__( 'URLs that have been pinged.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $post, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post->pinged ) ? true : false;
						},
					],
					'modified'          => [
						'type'        => Types::string(),
						'description' => esc_html__( 'The local modified time for a post. If a post was recently updated the modified field will change to match the corresponding time.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $post, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post->post_modified ) ? $post->post_modified : null;
						},
					],
					'modifiedGmt'       => [
						'type'        => Types::string(),
						'description' => esc_html__( 'The GMT modified time for a post. If a post was recently updated the modified field will change to match the corresponding time in GMT.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $post, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post->post_modified_gmt ) ? $post->post_modified_gmt : null;
						},
					],
					'parent'            => [
						'type'        => Types::post_object_union(),
						'description' => __( 'The parent of the object. The parent object can be of various types', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $post, array $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post->post_parent ) ? get_post( $post->post_parent ) : null;
						},
					],
					'editLast'          => [
						'type'        => Types::user(),
						'description' => __( 'The user that most recently edited the object', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $post, array $args, AppContext $context, ResolveInfo $info ) {
							$edit_last = get_post_meta( $post->ID, '_edit_last', true );

							return ! empty( $edit_last ) ? DataSource::resolve_user( absint( $edit_last ) ) : null;
						},
					],
					'editLock'          => [
						'type'        => new ObjectType( [
							'name'   => $single_name . 'editLock',
							'fields' => [
								'editTime' => [
									'type'        => Types::string(),
									'description' => __( 'The time when the object was last edited', 'wp-graphql' ),
									'resolve'     => function( $edit_lock, array $args, AppContext $context, ResolveInfo $info ) {
										$time = ( is_array( $edit_lock ) && ! empty( $edit_lock[0] ) ) ? $edit_lock[0] : null;

										return ! empty( $time ) ? date( 'Y-m-d H:i:s', $time ) : null;
									},
								],
								'user'     => [
									'type'        => Types::user(),
									'description' => __( 'The user that most recently edited the object', 'wp-graphql' ),
									'resolve'     => function( $edit_lock, array $args, AppContext $context, ResolveInfo $info ) {
										$user_id = ( is_array( $edit_lock ) && ! empty( $edit_lock[1] ) ) ? $edit_lock[1] : null;

										return ! empty( $user_id ) ? DataSource::resolve_user( $user_id ) : null;
									},
								],
							],
						] ),
						'description' => __( 'If a user has edited the object within the past 15 seconds, this will return the user and the time they last edited. Null if the edit lock doesn\'t exist or is greater than 15 seconds', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $post, array $args, AppContext $context, ResolveInfo $info ) {
							$edit_lock       = get_post_meta( $post->ID, '_edit_lock', true );
							$edit_lock_parts = explode( ':', $edit_lock );

							return ! empty( $edit_lock_parts ) ? $edit_lock_parts : null;
						},
					],
					'enclosure'         => [
						'type'        => Types::string(),
						'description' => __( 'The RSS enclosure for the object', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $post, array $args, AppContext $context, ResolveInfo $info ) {
							$enclosure = get_post_meta( $post->ID, 'enclosure', true );

							return ! empty( $enclosure ) ? $enclosure : null;
						},
					],
					'guid'              => [
						'type'        => Types::string(),
						'description' => esc_html__( 'The global unique identifier for this post. This currently matches the value stored in WP_Post->guid and the guid column in the `post_objects` database table.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $post, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post->guid ) ? $post->guid : null;
						},
					],
					'menuOrder'         => [
						'type'        => Types::int(),
						'description' => esc_html__( 'A field used for ordering posts. This is typically used with nav menu items or for special ordering of hierarchical content types.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $post, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post->menu_order ) ? absint( $post->menu_order ) : null;
						},
					],
					'desiredSlug' => [
						'type' => Types::string(),
						'description' => esc_html__( 'The desired slug of the post', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $post, $args, AppContext $context, ResolveInfo $info ) {
							$desired_slug = get_post_meta( $post->ID, '_wp_desired_post_slug', true );

							return ! empty( $desired_slug ) ? $desired_slug : null;
						},
					],
					'link'              => [
						'type'        => Types::string(),
						'description' => esc_html__( 'The desired slug of the post', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $post, $args, AppContext $context, ResolveInfo $info ) {
							$link = get_permalink( $post->ID );

							return ! empty( $link ) ? $link : null;
						},
					],
				];

				/**
				 * Add comment fields to the schema if the post_type supports "comments"
				 *
				 * @since 0.0.5
				 */
				if ( post_type_supports( $post_type_object->name, 'comments' ) ) {
					$fields['comments']     = CommentConnectionDefinition::connection();
					$fields['commentCount'] = [
						'type'        => Types::int(),
						'description' => esc_html__( 'The number of comments. Even though WPGraphQL denotes this field as an integer, in WordPress this field should be saved as a numeric string for compatability.', 'wp-graphql' ),
						'resolve'     => function( \WP_Post $post, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $post->comment_count ) ? absint( $post->comment_count ) : null;
						},
					];
				}

				/**
				 * Add term connections based on the allowed taxonomies that are also
				 * registered to the post_type
				 *
				 * @since 0.0.5
				 */
				if ( ! empty( $allowed_taxonomies ) && is_array( $allowed_taxonomies ) ) {
					foreach ( $allowed_taxonomies as $taxonomy ) {
						// If the taxonomy is in the array of taxonomies registered to the post_type
						if ( in_array( $taxonomy, get_object_taxonomies( $post_type_object->name ), true ) ) {
							$tax_object                                 = get_taxonomy( $taxonomy );
							$fields[ $tax_object->graphql_plural_name ] = TermObjectConnectionDefinition::connection( $tax_object );
						}
					}
				}

				/**
				 * This prepares the fields by sorting them and applying a filter for adjusting the schema.
				 * Because these fields are implemented via a closure the prepare_fields needs to be applied
				 * to the fields directly instead of being applied to all objects extending
				 * the WPObjectType class.
				 *
				 * @since 0.0.5
				 */
				return self::prepare_fields( $fields, $single_name );

			};

		} // End if().

		return ! empty( self::$fields[ $single_name ] ) ? self::$fields[ $single_name ] : null;

	}

	/**
	 * This customizes the fields for the mediaItem type ( attachment post_type) as the shape of the mediaItem Schema
	 * is different than a standard post
	 *
	 * @param array $fields
	 *
	 * @return array
	 */
	public function media_item_fields( $fields ) {

		/**
		 * Deprecate fields for the mediaItem type.
		 * These fields can still be queried, but are just not preferred for the mediaItem type
		 * @since 0.0.6
		 */
		$fields['excerpt']['isDeprecated'] = true;
		$fields['excerpt']['deprecationReason'] = __( 'Use the caption field instead of excerpt', 'wp-graphql' );
		$fields['content']['isDeprecated'] = true;
		$fields['content']['deprecationReason'] = __( 'Use the description field instead of content', 'wp-graphql' );

		/**
		 * Add new fields to the mediaItem type
		 * @since 0.0.6
		 */
		$new_fields = [
			'caption' => [
				'type' => Types::string(),
				'description' => esc_html__( 'The caption for the resource', 'wp-graphql' ),
				'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
					$caption = apply_filters( 'the_excerpt', apply_filters( 'get_the_excerpt', $post->post_excerpt, $post ) );

					return ! empty( $caption ) ? $caption : null;
				},
			],
			'altText' => [
				'type' => Types::string(),
				'description' => esc_html__( 'Alternative text to display when resource is not displayed', 'wp-graphql' ),
				'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
					return get_post_meta( $post->ID, '_wp_attachment_image_alt', true );
				},
			],
			'description' => [
				'type' => Types::string(),
				'description' => esc_html__( 'Description of the image (stored as post_content)', 'wp-graphql' ),
				'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
					return apply_filters( 'the_content', $post->post_content );
				},
			],
			'mediaType' => [
				'type' => Types::string(),
				'description' => esc_html__( 'Type of resource', 'wp-graphql' ),
				'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
					return wp_attachment_is_image( $post->ID ) ? 'image' : 'file';
				},
			],
			'sourceUrl' => [
				'type' => Types::string(),
				'description' => esc_html__( 'Url of the mediaItem', 'wp-graphql' ),
				'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
					return wp_get_attachment_url( $post->ID );
				},
			],
			'mediaDetails' => [
				'type' => self::media_details(),
				'description' => __( 'Details about the mediaItem', 'wp-graphql' ),
				'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
					return wp_get_attachment_metadata( $post->ID );
				},
			],

		];

		return array_merge( $fields, $new_fields );

	}

	/**
	 * This defines the media details object type that can be queried on mediaItems
	 * @return null|WPObjectType
	 * @since 0.0.6
	 */
	private static function media_details() {

		if ( null === self::$media_details ) {
			self::$media_details = new WPObjectType([
				'name' => 'mediaDetails',
				'fields' => function() {
					$fields = [
						'width' => [
							'type' => Types::int(),
							'description' => __( 'The width of the mediaItem', 'wp-graphql' ),
						],
						'height' => [
							'type' => Types::int(),
							'description' => __( 'The height of the mediaItem', 'wp-graphql' ),
						],
						'file' => [
							'type' => Types::string(),
							'description' => __( 'The height of the mediaItem', 'wp-graphql' ),
						],
						'sizes' => [
							'type' => Types::list_of( self::media_sizes() ),
							'description' => __( 'The available sizes of the mediaItem', 'wp-graphql' ),
							'resolve' => function( $media_details, $args, $context, ResolveInfo $info ) {
								if ( ! empty( $media_details['sizes'] ) ) {
									foreach ( $media_details['sizes'] as $size_name => $size ) {
										$sizes[] = $size;
										$sizes['name'] = $size_name;
									}
								}
								return ! empty( $sizes ) ? $sizes : null;
							},
						],
						'meta' => [
							'type' => self::media_item_meta(),
							'resolve' => function( $media_details, $args, $context, ResolveInfo $info ) {
								return ! empty( $media_details['image_meta'] ) ? $media_details['image_meta'] : null;
							},
						],
					];
					return self::prepare_fields( $fields, 'mediaDetails' );
				},
			]);
		} // End if().

		return ! empty( self::$media_details ) ? self::$media_details : null;

	}

	/**
	 * This defines the media item meta object type that can be queried on mediaItems
	 * @return null|WPObjectType
	 * @since 0.0.6
	 */
	private static function media_item_meta() {
		if ( null === self::$media_item_meta ) {
			self::$media_item_meta = new WPObjectType([
				'name' => 'meta',
				'fields' => [
					'aperture' => [
						'type' => Types::float(),
					],
					'credit' => [
						'type' => Types::string(),
					],
					'camera' => [
						'type' => Types::string(),
					],
					'caption' => [
						'type' => Types::string(),
					],
					'createdTimestamp' => [
						'type' => Types::int(),
						'resolve' => function( $meta, $args, $context, ResolveInfo $info ) {
							return ! empty( $meta['created_timestamp'] ) ? $meta['created_timestamp'] : null;
						},
					],
					'copyright' => [
						'type' => Types::string(),
					],
					'focalLength' => [
						'type' => Types::int(),
						'resolve' => function( $meta, $args, $context, ResolveInfo $info ) {
							return ! empty( $meta['focal_length'] ) ? $meta['focal_length'] : null;
						},
					],
					'iso' => [
						'type' => Types::int(),
					],
					'shutterSpeed' => [
						'type' => Types::float(),
						'resolve' => function( $meta, $args, $context, ResolveInfo $info ) {
							return ! empty( $meta['shutter_speed'] ) ? $meta['shutter_speed'] : null;
						},
					],
					'title' => [
						'type' => Types::string(),
					],
					'orientation' => [
						'type' => Types::string(),
					],
					'keywords' => [
						'type' => Types::list_of( Types::string() ),
					],
				],
			]);
		} // End if().
		return ! empty( self::$media_item_meta ) ? self::$media_item_meta : null;
	}

	/**
	 * This defines the sizes object type that can be queried on mediaItems within the mediaDetails
	 * @return null|WPObjectType
	 * @since 0.0.6
	 */
	private static function media_sizes() {

		if ( null === self::$media_sizes ) {
			self::$media_sizes = new WPObjectType([
				'name' => 'sizes',
				'fields' => [
					'name' => [
						'type' => Types::string(),
						'description' => __( 'The referenced size name', 'wp-graphql' ),
					],
					'file' => [
						'type' => Types::string(),
						'description' => __( 'The file of the for the referenced size', 'wp-graphql' ),
					],
					'width' => [
						'type' => Types::string(),
						'description' => __( 'The width of the for the referenced size', 'wp-graphql' ),
					],
					'height' => [
						'type' => Types::string(),
						'description' => __( 'The height of the for the referenced size', 'wp-graphql' ),
					],
					'mimeType' => [
						'type' => Types::string(),
						'description' => __( 'The mime type of the resource', 'wp-graphql' ),
					],
					'sourceUrl' => [
						'type' => Types::string(),
						'description' => __( 'The url of the for the referenced size', 'wp-graphql' ),
					],
				],
			]);
		} // End if().

		return ! empty( self::$media_sizes ) ? self::$media_sizes : null;

	}

}
