<?php
namespace DFM\WPGraphQL\Setup;
use DFM\WPGraphQL\Fields\EncloseMeField;
use DFM\WPGraphQL\Fields\MediaDetailsFieldType;
use DFM\WPGraphQL\Fields\ThumbnailIdField;
use DFM\WPGraphQL\Types\PostObject\PostObjectType;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;


/**
 * Class Init
 *
 * This sets up the PostType entities to be exposed to the RootQuery
 *
 * @package DFM\WPGraphQL\Queries\PostEntities
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
		 * Setup the root queries for post_types
		 * @since 0.0.2
		 */
		add_action( 'wpgraphql_root_queries', [ $this, 'setup_post_type_queries' ], 10, 1 );

		/**
		 * Add thumbnails to post_types that support the thumbnail feature
		 * @since 0.0.2
		 */
		add_action( 'wpgraphql_after_setup_post_type_queries', [ $this, 'add_thumbnails_to_post_types' ], 10, 1 );

		/**
		 * Add additional fields to the "post" post_type
		 * @since 0.0.2
		 */
		add_filter( 'wpgraphql_post_object_type_fields_post', [ $this, 'add_post_post_object_fields' ] );

		/**
		 * Set default query args for the attachment post_type
		 * @since 0.0.2
		 */
		add_action( 'wpgraphql_post_object_query_query_arg_defaults_attachment', [ $this, 'default_attachment_query_args' ] );

		/**
		 * Add fields to the attachment post_type
		 * @since 0.0.2
		 */
		add_filter( 'wpgraphql_post_object_type_fields_attachment', [ $this, 'add_attachment_post_object_fields' ], 10, 1 );

	}

	/**
	 * add_thumbnails_to_post_types
	 *
	 * Adds thumbnail fields to the post_types that have
	 * registered support for thumbnails
	 *
	 * @return void
	 * @since 0.0.2
	 */
	public function add_thumbnails_to_post_types() {

		// Retrieve the list of allowed_post_types
		$allowed_post_types = $this->get_allowed_post_types();

		// If there are allowed_post_types
		if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {

			// Loop through the $allowed_post_types
			foreach ( $allowed_post_types as $allowed_post_type ) {

				// If the post_type_supports the 'thumbnail' feature
				if ( post_type_supports( $allowed_post_type, 'thumbnail' ) ) {

					// Filter the post_type
					add_filter( 'wpgraphql_post_object_type_fields_' . $allowed_post_type, [
						$this,
						'add_thumbnail_fields'
					], 10, 1 );
				}

			}

		}

	}

	/**
	 * add_thumbnail_fields_to_post_types
	 *
	 * Filters the thumbnail fields into the post_type fields array
	 *
	 * @param $fields
	 * @return array
	 * @since 0.0.2
	 */
	public function add_thumbnail_fields( $fields ) {

		/**
		 * ThumbnailIdField
		 * @since 0.0.2
		 */
		$fields[] = new ThumbnailIdField();

		$fields[] = [
			'name' => 'thumbnail',
			'type' => new PostObjectType( [ 'post_type' => 'attachment', 'query_name' => 'Media' ] ),
			'resolve' => function( $value, array $args, ResolveInfo $info ) {

				// Get the thumbnail_id
				$thumbnail_id = get_post_thumbnail_id( $value->ID );

				// Return the object for the thumbnail, or nothing
				$thumbnail = ! empty( $thumbnail_id ) ? get_post( $thumbnail_id ) : null;

				return $thumbnail;

			}
		];

		return $fields;

	}


	/**
	 * show_post_types_in_graphql
	 *
	 * Filter the core post types to "show_in_graphql"
	 *
	 * Additional post_types can be given GraphQL support in the same way, by adding the
	 * "show_in_graphql" and optionally a "graphql_query_class". If no "graphql_query_class" is provided
	 * the default "PostObjectQuery" class will be used which provides the standard fields for all
	 * post objects.
	 *
	 * @since 0.0.2
	 */
	public function show_post_types_in_graphql(){

		global $wp_post_types;

		if ( isset( $wp_post_types['attachment'] ) ) {
			$wp_post_types['attachment']->show_in_graphql = true;
			$wp_post_types['attachment']->graphql_name = 'Media';
			$wp_post_types['attachment']->graphql_plural_name = 'Media';
			//$wp_post_types['attachment']->graphql_query_class = '\DFM\WPGraphQL\Types\Attachments\Query';
			//$wp_post_types['attachment']->graphql_mutation_class = '\DFM\WPGraphQL\Types\Attachments\Mutation';
			//$wp_post_types['attachment']->graphql_type_class = '\DFM\WPGraphQL\Types\Attachments\AttachmentType';
		}

		if ( isset( $wp_post_types['page'] ) ) {
			$wp_post_types['page']->show_in_graphql = true;
			$wp_post_types['page']->graphql_name = 'Page';
			$wp_post_types['page']->graphql_plural_name = 'Pages';
			//$wp_post_types['page']->graphql_query_class = '\DFM\WPGraphQL\Types\Pages\Query';
			//$wp_post_types['page']->graphql_mutation_class = '\DFM\WPGraphQL\Types\Pages\Mutation';
			//$wp_post_types['page']->graphql_type_class = '\DFM\WPGraphQL\Types\Pages\PageType';
		}

		if ( isset( $wp_post_types['post'] ) ) {
			$wp_post_types['post']->show_in_graphql = true;
			$wp_post_types['post']->graphql_name = 'Post';
			$wp_post_types['post']->graphql_plural_name = 'Posts';
			//$wp_post_types['post']->graphql_query_class = '\DFM\WPGraphQL\Types\Posts\Query';
			//$wp_post_types['post']->graphql_mutation_class = '\DFM\WPGraphQL\Types\Posts\Mutation';
			//$wp_post_types['post']->graphql_type_class = '\DFM\WPGraphQL\Types\Posts\PostType';
		}

	}

	/**
	 * get_allowed_post_types
	 *
	 * Get the post types that are allowed to be used in GraphQL
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
		 */
		$this->allowed_post_types = apply_filters( 'wpgraphql_post_queries_allowed_post_types', $post_types );

		/**
		 * Returns the list of allowed_post_types
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
	 * @param $fields
	 * @return array
	 */
	public function setup_post_type_queries( $fields ) {

		/**
		 * Add core post_types to show in GraohQL
		 * @since 0.0.2
		 */
		$this->show_post_types_in_graphql();

		/**
		 * Get the allowed post types that should be visible in GraphQL
		 */
		$allowed_post_types = $this->get_allowed_post_types();

		/**
		 * If there's a populated array of post_types, set up the proper queries
		 */
		if ( ! empty( $allowed_post_types ) && is_array( $allowed_post_types ) ) {

			/**
			 * Loop through each of the allowed_post_types
			 */
			foreach( $allowed_post_types as $allowed_post_type ) {

				/**
				 * Get the query class from the post_type_object
				 */
				$post_type_query_class = get_post_type_object( $allowed_post_type )->graphql_query_class;

				/**
				 * If the post_type has a "graphql_query_class" defined, use it
				 * Otherwise fall back to the standard PostObjectQuery class
				 */
				$class = ( ! empty( $post_type_query_class ) && class_exists( $post_type_query_class ) ) ? $post_type_query_class  : '\DFM\WPGraphQL\Types\PostObject\PostObjectQueryType';

				/**
				 * Set the Query Name to pass to the class
				 */
				$allowed_post_type_object = get_post_type_object( $allowed_post_type );
				$query_name = ! empty( $allowed_post_type_object->graphql_plural_name ) ? $allowed_post_type_object->graphql_plural_name : $this->post_type;

				/**
				 * Filter the $query_name
				 * @since 0.0.2
				 */
				$query_name = apply_filters( 'wpgraphql_post_type_queries_query_name', $query_name, $allowed_post_type_object );

				/**
				 * Make sure the name of the
				 */
				$query_name = preg_replace( '/[^A-Za-z0-9]/i', ' ', $query_name );
				$query_name = preg_replace( '/[^A-Za-z0-9]/i', '',  ucwords( $query_name ) );

				/**
				 * Adds the class to the RootQueryType
				 */
				$fields[] = new $class( [
					'post_type' => $allowed_post_type,
					'post_type_object' => $allowed_post_type_object,
					'query_name' => $query_name
				] );

				// @todo: add entry for getting a single Object instead of a list of objects

				/**
				 * Run an action after each allowed_post_type is added to the root_query
				 * @since 0.0.2
				 */
				do_action( 'wpgraphql_after_setup_post_type_query_' . $allowed_post_type, $allowed_post_type, $allowed_post_type_object, $this->allowed_post_types );

			}

		}

		/**
		 * Run an action after the post_type queries have been setup
		 */
		do_action( 'wpgraphql_after_setup_post_type_queries', $allowed_post_types );

		/**
		 * Returns the fields
		 */
		return $fields;

	}

	/**
	 * add_post_post_object_fields
	 *
	 * Adds additional fields to the "post" post_type
	 *
	 * @since 0.0.2
	 */
	public function add_post_post_object_fields( $fields ) {

		/**
		 * EncloseMeField
		 * @since 0.0.2
		 */
		$fields[] = new EncloseMeField();

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
	 * @return array
	 * @since 0.0.2
	 */
	public function add_attachment_post_object_fields( $fields ) {

		$fields[] = [
			'name' => 'caption',
			'type' => new StringType(),
			'description' => __( 'The caption for the resource', 'wp-graphql' ),
			'resolve' => function( $value, array $args, ResolveInfo $info ) {
				return esc_html( $value->post_excerpt );
			}
		];

		$fields[] = [
			'name' => 'alt_text',
			'type' => new StringType(),
			'description' => __( 'Alternative text to display when resource is not displayed', 'wp-graphql' ),
			'resolve' => function( $value, array $args, ResolveInfo $info ) {
				return esc_html( get_post_meta( $value->ID, '_wp_attachment_image_alt', true ) );
			}
		];

		$fields[] = [
			'name' => 'description',
			'type' => new StringType(),
			'description' => __( 'The description for the resource', 'wp-graphql' ),
			'resolve' => function( $value, array $args, ResolveInfo $info ) {
				return esc_html( $value->post_excerpt );
			}
		];

		$fields[] = [
			'name' => 'media_type',
			'type' => new StringType(),
			'description' => __( 'Type of resource', 'wp-graphql' ),
			'resolve' => function( $value, array $args, ResolveInfo $info ) {
				return wp_attachment_is_image( $value->ID ) ? 'image' : 'file';
			}
		];

		$fields[] = [
			'name' => 'mime_type',
			'type' => new StringType(),
			'description' => __( 'Mime type of resource', 'wp-graphql' ),
			'resolve' => function( $value, array $args, ResolveInfo $info ) {
				return esc_html( $value->post_mime_type );
			}
		];

		// @todo: add support for media details

		$fields[] = [
			'name' => 'associtated_post_id',
			'type' => new IntType(),
			'description' => __( 'The id for the associated post of the resource.', 'wp-graphql' ),
			'resolve' => function( $value, array $args, ResolveInfo $info ) {
				return ! empty( $value->post_parent ) ? (int) $value->post_parent : null;
			}
		];

		$fields[] = [
			'name' => 'source_url',
			'type' => new IntType(),
			'description' => __( 'The id for the associated post of the resource.', 'wp-graphql' ),
			'resolve' => function( $value, array $args, ResolveInfo $info ) {
				return wp_get_attachment_url( $value->ID );
			}
		];

		$fields[] = [
			'name' => 'media_details',
			'type' => new MediaDetailsFieldType(),
			'description' => __( 'Details about the media object.', 'wp-graphql' ),
			'resolve' => function( $value, array $args, ResolveInfo $info ) {
				return $value;
			}
		];

		return $fields;

	}

}