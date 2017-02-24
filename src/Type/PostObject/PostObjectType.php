<?php
namespace WPGraphQL\Type\PostObject;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\Connections;
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
 * @since 0.0.5
 */
class PostObjectType extends WPObjectType {

	/**
	 * Holds the $fields definition for the PostObjectType
	 * @var $fields
	 */
	private static $fields;

	/**
	 * Holds the post_type_object
	 * @var object $post_type_object
	 */
	private static $post_type_object;

	/**
	 * PostObjectType constructor.
	 *
	 * @param array $post_type
	 * @since 0.0.5
	 */
	public function __construct( $post_type ) {

		/**
		 * Get the post_type_object from the post_type and store it
		 * for later use
		 * @since 0.0.5
		 */
		self::$post_type_object = get_post_type_object( $post_type );

		$config = [
			'name' => self::$post_type_object->graphql_single_name,
			'description' => sprintf( __( 'The %s object type', 'wp-graphql' ), self::$post_type_object->graphql_single_name ),
			'fields' => self::fields( self::$post_type_object ),
			'interfaces' => [ self::node_interface() ],
		];
		parent::__construct( $config );
	}

	/**
	 * fields
	 * This defines the fields for PostObjectTypes
	 *
	 * @param $post_type_object
	 * @return \GraphQL\Type\Definition\FieldDefinition|mixed|null
	 * @since 0.0.5
	 */
	private static function fields( $post_type_object ) {

		/**
		 * Get the $single_name out of the post_type_object
		 * @since 0.0.5
		 */
		$single_name = self::$post_type_object->graphql_single_name;

		/**
		 * If no fields have been defined for this type already,
		 * make sure the $fields var is an empty array
		 * @since 0.0.5
		 */
		if ( null === self::$fields ) {
			self::$fields = [];
		}

		/**
		 * If the $fields haven't already been defined for this type,
		 * define the fields
		 * @since 0.0.5
		 */
		if ( empty( self::$fields[ $single_name ] ) ) {

			/**
			 * Get the taxonomies that are allowed in WPGraphQL
			 * @since 0.0.5
			 */
			$allowed_taxonomies = \WPGraphQL::$allowed_taxonomies;

			/**
			 * Define the fields for the post_type
			 * @return mixed
			 * @since 0.0.5
			 */
			self::$fields[ $single_name ] = function() use ( $single_name, $post_type_object, $allowed_taxonomies ) {
				$fields = [
					'id' => [
						'type' => Types::non_null( Types::id() ),
						'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
							return ( ! empty( $post->post_type ) && ! empty( $post->ID ) ) ? Relay::toGlobalId( $post->post_type, $post->ID ) : null;
						},
					],
					$single_name . 'Id' => array(
						'type' => Types::non_null( Types::int() ),
						'description' => esc_html__( 'The id field matches the WP_Post->ID field.', 'wp-graphql' ),
						'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
							return absint( $post->ID );
						},
					),
					'author' => array(
						'type' => Types::user(),
						'description' => esc_html__( "The author field will return a queryable User type matching the post's author.", 'wp-graphql' ),
						'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
							return ! empty( $post->post_author ) ? new \WP_User( $post->post_author ) : null;
						},
					),
					'date' => array(
						'type' => Types::string(),
						'description' => esc_html__( 'Post publishing date.', 'wp-graphql' ),
						'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
							return ! empty( $post->post_date ) ? $post->post_date : null;
						},
					),
					'dateGmt' => array(
						'type' => Types::string(),
						'description' => esc_html__( 'The publishing date set in GMT.', 'wp-graphql' ),
						'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
							return ! empty( $post->post_date_gmt ) ? $post->post_date_gmt : null;
						},
					),
					'content' => array(
						'type' => Types::string(),
						'description' => esc_html__( 'The content of the post. This is currently just the raw content. An amendment to support rendered content needs to be made.', 'wp-graphql' ),
						'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
							return apply_filters( 'the_content', $post->post_content );
						},
					),
					'title' => array(
						'type' => Types::string(),
						'description' => esc_html__( 'The title of the post. This is currently just the raw title. An amendment to support rendered title needs to be made.', 'wp-graphql' ),
						'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
							return ! empty( $post->post_title ) ? $post->post_title : null;
						},
					),
					'excerpt' => array(
						'type' => Types::string(),
						'description' => esc_html__( 'The excerpt of the post. This is currently just the raw excerpt. An amendment to support rendered excerpts needs to be made.', 'wp-graphql' ),
						'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
							$excerpt = apply_filters( 'the_excerpt', apply_filters( 'get_the_excerpt', $post->post_excerpt, $post ) );

							return ! empty( $excerpt ) ? $excerpt : null;
						},
					),
					'status' => array(
						'type' => Types::string(),
						'description' => esc_html__( 'The current status of the object', 'wp-graphql' ),
						'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
							return ! empty( $post->post_status ) ? $post->post_status : null;
						},
					),
					'commentStatus' => array(
						'type' => Types::string(),
						'description' => esc_html__( 'Whether the comments are open or closed for this particular post.', 'wp-graphql' ),
						'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
							return ! empty( $post->comment_status ) ? $post->comment_status : null;
						},
					),
					'pingStatus' => array(
						'type' => Types::string(),
						'description' => esc_html__( 'Whether the pings are open or closed for this particular post.', 'wp-graphql' ),
						'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
							return ! empty( $post->ping_status ) ? $post->ping_status : null;
						},
					),
					'slug' => array(
						'type' => Types::string(),
						'description' => esc_html__( 'The uri slug for the post. This is equivalent to the WP_Post->post_name field and the post_name column in the database for the `post_objects` table.', 'wp-graphql' ),
						'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
							return ! empty( $post->post_name ) ? $post->post_name : null;
						},
					),
					'toPing' => array(
						'type' => Types::boolean(),
						'description' => esc_html__( 'URLs queued to be pinged.', 'wp-graphql' ),
						'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
							return ! empty( $post->to_ping ) ? true : false;
						},
					),
					'pinged' => array(
						'type' => Types::boolean(),
						'description' => esc_html__( 'URLs that have been pinged.', 'wp-graphql' ),
						'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
							return ! empty( $post->pinged ) ? true : false;
						},
					),
					'modified' => array(
						'type' => Types::string(),
						'description' => esc_html__( 'The local modified time for a post. If a post was recently updated the modified field will change to match the corresponding time.', 'wp-graphql' ),
						'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
							return ! empty( $post->post_modified ) ? $post->post_modified : null;
						},
					),
					'modifiedGmt' => array(
						'type' => Types::string(),
						'description' => esc_html__( 'The GMT modified time for a post. If a post was recently updated the modified field will change to match the corresponding time in GMT.', 'wp-graphql' ),
						'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
							return ! empty( $post->post_modified_gmt ) ? $post->post_modified_gmt : null;
						},
					),
					// @todo: add the parent
					'editLast' => [
						'type' => Types::user(),
						'description' => __( 'The user that most recently edited the object', 'wp-graphql' ),
						'resolve' => function( \WP_Post $post, array $args, $context, ResolveInfo $info ) {
							$edit_last = get_post_meta( $post->ID, '_edit_last', true );

							return ! empty( $edit_last ) ? DataSource::resolve_user( absint( $edit_last ) ) : null;
						},
					],
					'editLock' => [
						'type' => new ObjectType( [
							'name' => $single_name . 'editLock',
							'fields' => [
								'editTime' => [
									'type' => Types::string(),
									'description' => __( 'The time when the object was last edited', 'wp-graphql' ),
									'resolve' => function( $edit_lock, array $args, $context, ResolveInfo $info ) {
										$time = ( is_array( $edit_lock ) && ! empty( $edit_lock[0] ) ) ? $edit_lock[0] : null;

										return ! empty( $time ) ? date( 'Y-m-d H:i:s', $time ) : null;
									},
								],
								'user' => [
									'type' => Types::user(),
									'description' => __( 'The user that most recently edited the object', 'wp-graphql' ),
									'resolve' => function( $edit_lock, array $args, $context, ResolveInfo $info ) {
										$user_id = ( is_array( $edit_lock ) && ! empty( $edit_lock[1] ) ) ? $edit_lock[1] : null;

										return ! empty( $user_id ) ? DataSource::resolve_user( $user_id ) : null;
									},
								],
							],
						] ),
						'description' => __( 'If a user has edited the object within the past 15 seconds, this will return the user and the time they last edited. Null if the edit lock doesn\'t exist or is greater than 15 seconds', 'wp-graphql' ),
						'resolve' => function( \WP_Post $post, array $args, $context, ResolveInfo $info ) {
							$edit_lock = get_post_meta( $post->ID, '_edit_lock', true );
							$edit_lock_parts = explode( ':', $edit_lock );
							$edit_time = ( is_array( $edit_lock_parts ) && ! empty( $edit_lock_parts[0] ) ) ? $edit_lock_parts[0] : null;

							/**
							 * If the $edit_time is older than 15 seconds ago, let's not return anything as we don't
							 * consider the object locked.
							 * @since 0.0.5
							 */
							if ( $edit_time < ( strtotime( 'now' ) - 15 ) ) {
								$edit_lock_parts = null;
							}

							return ! empty( $edit_lock_parts ) ? $edit_lock_parts : null;
						},
					],
					'enclosure' => [
						'type' => Types::string(),
						'description' => __( 'The RSS enclosure for the object', 'wp-graphql' ),
						'resolve' => function( \WP_Post $post, array $args, $context, ResolveInfo $info ) {
							$enclosure = get_post_meta( $post->ID, 'enclosure', true );

							return ! empty( $enclosure ) ? $enclosure : null;
						},
					],
					'guid' => array(
						'type' => Types::string(),
						'description' => esc_html__( 'The global unique identifier for this post. This currently matches the value stored in WP_Post->guid and the guid column in the `post_objects` database table.', 'wp-graphql' ),
						'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
							return ! empty( $post->guid ) ? $post->guid : null;
						},
					),
					'menuOrder' => array(
						'type' => Types::int(),
						'description' => esc_html__( 'A field used for ordering posts. This is typically used with nav menu items or for special ordering of hierarchical content types.', 'wp-graphql' ),
						'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
							return ! empty( $post->menu_order ) ? absint( $post->menu_order ) : null;
						},
					),
					'mimeType' => array(
						'type' => Types::string(),
						'description' => esc_html__( 'If the post is an attachment or a media file, this field will carry the corresponding MIME type. This field is equivalent to the value of WP_Post->post_mime_type and the post_mime_type column in the `post_objects` database table.', 'wp-graphql' ),
						'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
							return ! empty( $post->post_mime_type ) ? $post->post_mime_type : null;
						},
					),
					'desiredSlug' => [
						'type' => Types::string(),
						'description' => esc_html__( 'The desired slug of the post', 'wp-graphql' ),
						'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
							$desired_slug = get_post_meta( $post->ID, '_wp_desired_post_slug', true );

							return ! empty( $desired_slug ) ? $desired_slug : null;
						},
					],
					'link' => [
						'type' => Types::string(),
						'description' => esc_html__( 'The desired slug of the post', 'wp-graphql' ),
						'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
							$link = get_permalink( $post->ID );

							return ! empty( $link ) ? $link : null;
						},
					],
				];

				/**
				 * Add comment fields to the schema if the post_type supports "comments"
				 * @since 0.0.5
				 */
				if ( post_type_supports( $post_type_object->name, 'comments' ) ) {
					$fields['comments'] = CommentConnectionDefinition::connection();
					$fields['commentCount'] = [
						'type' => Types::int(),
						'description' => esc_html__( 'The number of comments. Even though WPGraphQL denotes this field as an integer, in WordPress this field should be saved as a numeric string for compatability.', 'wp-graphql' ),
						'resolve' => function( \WP_Post $post, $args, $context, ResolveInfo $info ) {
							return ! empty( $post->comment_count ) ? absint( $post->comment_count ) : null;
						},
					];
				}

				/**
				 * Add term connections based on the allowed taxonomies that are also
				 * registered to the post_type
				 * @since 0.0.5
				 */
				if ( ! empty( $allowed_taxonomies ) && is_array( $allowed_taxonomies ) ) {
					foreach ( $allowed_taxonomies as $taxonomy ) {
						// If the taxonomy is in the array of taxonomies registered to the post_type
						if ( in_array( $taxonomy, get_object_taxonomies( $post_type_object->name ), true ) ) {
							$tax_object = get_taxonomy( $taxonomy );
							$fields[ $tax_object->graphql_plural_name ] = TermObjectConnectionDefinition::connection( $tax_object );
						}
					}
				}

				/**
				 * This prepares the fields by sorting them and applying a filter for adjusting the schema.
				 * Because these fields are implemented via a closure the prepare_fields needs to be applied
				 * to the fields directly instead of being applied to all objects extending
				 * the WPObjectType class.
				 * @since 0.0.5
				 */
				return self::prepare_fields( $fields, $single_name );

			};

		}

		return ! empty( self::$fields[ $single_name ] ) ? self::$fields[ $single_name ] : null;

	}

}
