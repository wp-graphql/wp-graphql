<?php
namespace WPGraphQL\Setup;

use WPGraphQL\Types\PostObject\PostObjectType;
use WPGraphQL\Utils\Fields;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Object\ObjectType;
use Youshido\GraphQL\Type\Scalar\BooleanType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;


/**
 * Class PostEntities
 *
 * This sets up the PostType entities to be exposed to the RootQuery
 *
 * @package WPGraphQL\Queries\PostEntities
 * @since 0.0.2
 */
class PostEntities {

	/**
	 * allowed_post_types
	 *
	 * Holds an array of the post_types allowed to be exposed in the GraphQL Queries
	 *
	 * @var array
	 * @since 0.0.2
	 */
	public $allowed_post_types = [];

	/**
	 * PostsQueries constructor.
	 *
	 * Placeholder
	 *
	 * @since 0.0.2
	 */
	public function __construct() {
		// Placeholder
	}

	/**
	 * init
	 *
	 * Setup the root queries for each allowed_post_type
	 *
	 * @return void
	 * @since 0.0.2
	 *
	 */
	public function init() {

		/**
		 * Define what post_types should be part of the GraphQL Schema
		 */
		add_action( 'graphql_init', [ $this, 'show_in_graphql' ], 5 );

		/**
		 * Setup the root queries for post_types
		 * @since 0.0.2
		 */
		add_action( 'graphql_root_queries', [ $this, 'setup_post_type_queries' ], 5, 1 );

		/**
		 * Add thumbnails and other dynamic fields to post_types that support the feature
		 * @since 0.0.2
		 */
		add_action( 'graphql_after_setup_post_type_queries', [ $this, 'dynamic_fields' ], 5 );

		/**
		 * Set default query args for the attachment post_type
		 * @since 0.0.2
		 */
		add_action( 'graphql_post_object_query_query_arg_defaults_attachment', [
			$this,
			'default_attachment_query_args',
		] );

	}

	/**
	 * show_in_graphql
	 *
	 * Modify the global $wp_post_types, adding the property "show_in_graphql"
	 * to attachment, post, and page, and providing additional graphql properties
	 *
	 * @since 0.0.2
	 */
	public function show_in_graphql() {

		global $wp_post_types;

		if ( isset( $wp_post_types['attachment'] ) ) {
			$wp_post_types['attachment']->show_in_graphql     = true;
			$wp_post_types['attachment']->graphql_name        = 'MediaItem';
			$wp_post_types['attachment']->graphql_plural_name = 'MediaItems';
		}

		if ( isset( $wp_post_types['page'] ) ) {
			$wp_post_types['page']->show_in_graphql     = true;
			$wp_post_types['page']->graphql_name        = 'Page';
			$wp_post_types['page']->graphql_plural_name = 'Pages';
		}

		if ( isset( $wp_post_types['post'] ) ) {
			$wp_post_types['post']->show_in_graphql     = true;
			$wp_post_types['post']->graphql_name        = 'Post';
			$wp_post_types['post']->graphql_plural_name = 'Posts';
		}

	}

	/**
	 * get_allowed_post_types
	 *
	 * Get the post types that are allowed to be used in GraphQL.
	 * This gets all post_types that are set to show_in_graphql, but allows
	 * for external code (plugins/theme) to filter the list of allowed_post_types
	 * to add/remove additional post_types
	 *
	 * @return array
	 * @since 0.0.2
	 */
	public function get_allowed_post_types() {

		/**
		 * Get all post_types that have been registered to "show_in_graphql"
		 */
		$post_types = get_post_types( [ 'show_in_graphql' => true ] );

		/**
		 * Define the $allowed_post_types to be exposed by GraphQL Queries
		 * Pass through a filter to allow the post_types to be modified (for example if
		 * a certain post_type should not be exposed to the GraphQL API)
		 *
		 * @since 0.0.2
		 */
		$this->allowed_post_types = apply_filters( 'graphql_post_entities_allowed_post_types', $post_types );

		/**
		 * Returns the array of allowed_post_types
		 */
		return $this->allowed_post_types;

	}

	/**
	 * setup_post_type_queries
	 *
	 * This sets up post_type_queries for all post_types that have "set_in_graphql"
	 * set to "true" on their post_type_object
	 *
	 * @since 0.0.2
	 *
	 * @param $fields
	 *
	 * @return array
	 */
	public function setup_post_type_queries( $fields ) {

		/**
		 * Instantiate the Utils/Fields class
		 */
		$field_utils = new Fields();

		/**
		 * Get the allowed post types that should be part of in GraphQL
		 */
		$allowed_post_types = $this->get_allowed_post_types();

		/**
		 * If there's a populated array of post_types, set up the proper queries
		 */
		if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {

			/**
			 * Loop through each of the allowed_post_types
			 */
			foreach ( $allowed_post_types as $allowed_post_type ) {

				/**
				 * Get the query class from the post_type_object
				 */
				$post_type_query_class = get_post_type_object( $allowed_post_type )->graphql_query_class;

				/**
				 * If the post_type has a "graphql_query_class" defined, use it
				 * Otherwise fall back to the standard PostObjectQuery class
				 */
				$class = ( ! empty( $post_type_query_class ) && class_exists( $post_type_query_class ) ) ? $post_type_query_class : '\WPGraphQL\Types\PostObject\PostObjectQueryType';

				/**
				 * Configure the field names to pass to the fields
				 */
				$allowed_post_type_object = get_post_type_object( $allowed_post_type );
				$plural_query_name        = ! empty( $allowed_post_type_object->graphql_plural_name ) ? $allowed_post_type_object->graphql_plural_name : $this->post_type;
				$single_query_name        = ! empty( $allowed_post_type_object->graphql_name ) ? $allowed_post_type_object->graphql_name : $this->post_type . 'Items';

				/**
				 * Make sure the name of the queries are formatted to play nice with GraphQL
				 *
				 * @since 0.0.2
				 */
				$single_query_name = $field_utils->format_field_name( $single_query_name );
				$plural_query_name = $field_utils->format_field_name( $plural_query_name );

				/**
				 * Adds a field to get a single PostObjectType by ID
				 *
				 * ex: Post(id: Int!): Post
				 *
				 * @since 0.0.2
				 */
				$fields[ $single_query_name ] = [
					'name'    => $single_query_name,
					'type'    => new PostObjectType( [
						'query_name' => $single_query_name,
						'post_type'  => $allowed_post_type,
					] ),
					'args'    => [
						'id' => new NonNullType( new IntType() ),
					],
					'resolve' => function( $value, array $args, ResolveInfo $info ) {
						$post_object = get_post( $args['id'] );

						return ! empty( $post_object ) ? $post_object : null;
					},
				];

				/**
				 * Adds a field to query a list of PostObjectTypes items with
				 * additional query information returned (for pagination, etc)
				 *
				 * @since 0.0.1
				 */
				$fields[ $plural_query_name ] = new $class( [
					'post_type'        => $allowed_post_type,
					'post_type_object' => $allowed_post_type_object,
					'query_name'       => $plural_query_name,
				] );

				/**
				 * Run an action after each allowed_post_type is added to the root_query
				 * @since 0.0.2
				 */
				do_action( 'graphql_after_setup_post_type_query_' . $allowed_post_type, $allowed_post_type, $allowed_post_type_object, $this->get_allowed_post_types() );

			}
		}

		/**
		 * Run an action after the post_type queries have been setup
		 */
		do_action( 'graphql_after_setup_post_type_queries', $this->get_allowed_post_types() );

		/**
		 * Returns the fields
		 */
		return $fields;

	}

	/**
	 * dynamic_fields
	 *
	 * This adds dynamic fields based on various WordPress configuration settings.
	 * For example, this adds the "thumbnail" field to the post_types that have support for it,
	 * and adds the appropriate term field(s) for taxonomies that are registered to the post_type
	 *
	 * @return void
	 * @since 0.0.2
	 */
	public function dynamic_fields() {

		/**
		 * Add additional fields to the "post" post_type
		 * @since 0.0.2
		 */
		add_filter( 'graphql_post_object_type_fields_post', [ $this, 'add_fields_to_the_post_post_type' ], 10, 1 );

		/**
		 * Add fields to the attachment post_type
		 * @since 0.0.2
		 */
		add_filter( 'graphql_post_object_type_fields_attachment', [
			$this,
			'add_attachment_post_object_fields',
		], 10, 1 );

		// Retrieve the list of allowed_post_types
		$allowed_post_types = $this->get_allowed_post_types();

		// If there are allowed_post_types
		if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {

			// Loop through the $allowed_post_types
			foreach ( $allowed_post_types as $allowed_post_type ) {

				/**
				 * Add the thumbnail field to the post_types that have post_type_support for the "thumbnail"
				 *
				 * @since 0.0.2
				 */
				if ( post_type_supports( $allowed_post_type, 'thumbnail' ) ) {

					add_filter( 'graphql_post_object_type_fields_' . $allowed_post_type, function( $fields ) {

						$fields['thumbnail'] = [
							'name'    => 'thumbnail',
							'type'    => new PostObjectType( [
								'post_type'  => 'attachment',
								'query_name' => 'Thumbnail',
							] ),
							'resolve' => function( $value, array $args, ResolveInfo $info ) {

								// Get the thumbnail_id
								$thumbnail_id = get_post_thumbnail_id( $value->ID );

								// Return the object for the thumbnail, or nothing
								$thumbnail = ! empty( $thumbnail_id ) ? get_post( $thumbnail_id ) : null;

								return $thumbnail;

							},
						];

						return $fields;

					}, 10, 1 );

				}

				/**
				 * If the post_type has post_type_support for 'author' this adds the author field(s)
				 * @todo: add Author field that returns a full Author object
				 *
				 * @since 0.0.2
				 */
				if ( post_type_supports( $allowed_post_type, 'author' ) ) {

					add_filter( 'graphql_post_object_type_fields_' . $allowed_post_type, function( $fields ) {

						$fields['author_id'] = [
							'name'        => 'author_id',
							'type'        => new IntType(),
							'description' => __( 'The id for the author of the object. (post_author)', 'wp-graphql' ),
							'resolve'     => function( $value, array $args, ResolveInfo $info ) {
								return ! empty( $value->post_author ) ? absint( $value->post_author ) : null;
							},
						];

						return $fields;

					}, 10, 1 );

				}

				/**
				 *
				 */
				if ( post_type_supports( $allowed_post_type, 'comments' ) ) {

					// @todo: once we add the CommentType, we'll need to add the Comments field
					// to the post_types that support comments

				}

				/**
				 * If revisions is an allowed_post_type and this post_type has post_type_support for revisions,
				 * add the revisions field.
				 *
				 * @since 0.0.2
				 */
				if ( post_type_supports( $allowed_post_type, 'revisions' ) && in_array( 'revisions', $allowed_post_types, true ) ) {

					add_filter( 'graphql_post_object_type_fields_' . $allowed_post_type, function( $fields ) {

						$fields['revisions'] = [
							'name'        => 'revisions',
							'type'        => new ListType( new PostObjectType( [
								'post_type'  => 'revisions',
								'query_name' => 'revisions',
							] ) ),
							'description' => __( 'Returns revisions of the specified post', 'wp-graphql' ),
							'resolve'     => function( $value, array $args, ResolveInfo $info ) {

								$revisions = wp_get_post_revisions( $value->ID );

								return ! empty( $revisions ) ? $revisions : null;

							},
						];

						return $fields;

					}, 10, 1 );

				}

				/**
				 * Add page-attributes fields to the post_types that have post_type_support for "page-attributes"
				 *
				 * @since 0.0.2
				 */
				if ( post_type_supports( $allowed_post_type, 'page-attributes' ) ) {

					add_filter( 'graphql_post_object_type_fields_' . $allowed_post_type, function( $fields ) {

						$fields['menu_order'] = [
							'name'        => 'menu_order',
							'type'        => new StringType(),
							'description' => __( 'Order value as set through page-attribute when enabled', 'wp-graphql' ),
							'resolve'     => function( $value, array $args, ResolveInfo $info ) {
								return ! empty( $value->menu_order ) ? $value->menu_order : null;
							},
						];

						$fields['page_template'] = [
							'name'        => 'page_template',
							'type'        => new StringType(),
							'description' => __( 'The page-template that the object is set to use', '' ),
							'resolve'     => function( $value, array $args, ResolveInfo $info ) {

								$page_template = get_post_meta( $value->ID, '_wp_page_template', true );

								return ! empty( $page_template ) ? $page_template : null;

							},
						];

						return $fields;

					}, 10, 1 );

				}
			}
		}

	}

	/**
	 * add_fields_to_the_post_post_type
	 *
	 * Adds additional fields to the "post" post_type that wp core uses to some
	 * capacity
	 *
	 * @since 0.0.2
	 */
	public function add_fields_to_the_post_post_type( $fields ) {

		/**
		 * EncloseMeField
		 * @since 0.0.2
		 */
		$fields['enclose_me'] = [
			'name'        => 'enclose_me',
			'type'        => new BooleanType(),
			'description' => __( 'Whether or not the post needs processed for enclosure', 'wp-graphql' ),
			'resolve'     => function( $value, array $args, ResolveInfo $info ) {
				$enclose_me = get_post_meta( $value->ID, '_enclose_me', true );

				return ! empty( $enclose_me ) ? true : false;
			},
		];

		/**
		 * Return the $fields
		 */
		return $fields;

	}

	/**
	 * default_attachment_query_args
	 *
	 * Sets default values for the attachment query
	 *
	 * @param $args
	 *
	 * @return mixed
	 * @since 0.0.2
	 */
	public function default_attachment_query_args( $args ) {

		$args['post_status'] = 'inherit';

		return $args;

	}

	/**
	 * This adds additional fields to the Attachment post_object
	 *
	 * @param $fields
	 *
	 * @return array
	 * @since 0.0.2
	 */
	public function add_attachment_post_object_fields( $fields ) {

		$fields['caption'] = [
			'name'        => 'caption',
			'type'        => new StringType(),
			'description' => __( 'The caption for the resource', 'wp-graphql' ),
			'resolve'     => function( $value, array $args, ResolveInfo $info ) {
				return esc_html( $value->post_excerpt );
			},
		];

		$fields['alt_text'] = [
			'name'        => 'alt_text',
			'type'        => new StringType(),
			'description' => __( 'Alternative text to display when resource is not displayed', 'wp-graphql' ),
			'resolve'     => function( $value, array $args, ResolveInfo $info ) {
				return esc_html( get_post_meta( $value->ID, '_wp_attachment_image_alt', true ) );
			},
		];

		$fields['description'] = [
			'name'        => 'description',
			'type'        => new StringType(),
			'description' => __( 'The description for the resource', 'wp-graphql' ),
			'resolve'     => function( $value, array $args, ResolveInfo $info ) {
				return esc_html( $value->post_excerpt );
			},
		];

		$fields['media_type'] = [
			'name'        => 'media_type',
			'type'        => new StringType(),
			'description' => __( 'Type of resource', 'wp-graphql' ),
			'resolve'     => function( $value, array $args, ResolveInfo $info ) {
				return wp_attachment_is_image( $value->ID ) ? 'image' : 'file';
			},
		];

		$fields['mime_type'] = [
			'name'        => 'mime_type',
			'type'        => new StringType(),
			'description' => __( 'Mime type of resource', 'wp-graphql' ),
			'resolve'     => function( $value, array $args, ResolveInfo $info ) {
				return esc_html( $value->post_mime_type );
			},
		];

		/**
		 * @todo: so, I want to be able to have the full PostObjectType returned instead of just an ID, but
		 * since we don't know what "Type" the parent is (as it can be any type), it's hard to add schema
		 * for an unknown "type"
		 */

		$fields['associated_post_id'] = [
			'name'        => 'associated_post_id',
			'type'        => new IntType(),
			'description' => __( 'The id for the associated post of the resource.', 'wp-graphql' ),
			'resolve'     => function( $value, array $args, ResolveInfo $info ) {
				return ! empty( $value->post_parent ) ? (int) $value->post_parent : null;
			},
		];

		$fields['source_url'] = [
			'name'        => 'source_url',
			'type'        => new IntType(),
			'description' => __( 'The id for the associated post of the resource.', 'wp-graphql' ),
			'resolve'     => function( $value, array $args, ResolveInfo $info ) {
				return wp_get_attachment_url( $value->ID );
			},
		];

		$fields['image_src'] = [
			'name'        => 'image_src',
			'type'        => new ObjectType( [
				'name'        => 'image_src',
				'description' => __( 'Retrieve an image to represent an attachment. (wp_get_attachment_image_src)', 'wp-graphql' ),
				'args'        => [
					'size' => [
						'name'        => 'size',
						'type'        => new StringType(),
						'description' => __( 'Any valid image size. Default value: thumbnail', 'wp-graphql' ),
					],
					'icon' => [
						'name'        => 'icon',
						'type'        => new BooleanType(),
						'description' => __( 'Whether the image should be treated as an icon.', 'wp-graphql' ),
					],
				],
				'fields'      => [
					'src'    => [
						'name'        => 'src',
						'type'        => new StringType(),
						'description' => __( 'The full path to the resource file.', 'wp-graphql' ),
						'resolve'     => function( $value, array $args, ResolveInfo $info ) {
							return ! empty( $value[0] ) ? $value[0] : null;
						},
					],
					'width'  => [
						'name'        => 'width',
						'type'        => new StringType(),
						'description' => __( 'The width of the resource file', 'wp-graphql' ),
						'resolve'     => function( $value, array $args, ResolveInfo $info ) {
							return ! empty( $value[1] ) ? absint( $value[1] ) : '';
						},
					],
					'height' => [
						'name'        => 'height',
						'type'        => new StringType(),
						'description' => __( 'The height of the resource file.', 'wp-graphql' ),
						'resolve'     => function( $value, array $args, ResolveInfo $info ) {
							return ! empty( $value[2] ) ? absint( $value[2] ) : '';
						},
					],
				],
			] ),
			'description' => __( 'Details about the media object.', 'wp-graphql' ),
			'resolve'     => function( $value, array $args, ResolveInfo $info ) {

				$size    = ! empty( $args['size'] ) ? $args['size'] : null;
				$img_src = wp_get_attachment_image_src( $value->ID, $size );

				return ! empty( $img_src ) ? $img_src : null;

			},
		];

		return $fields;

	}
}