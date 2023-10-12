<?php

namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use WP_Post_Type;
use WPGraphQL\AppContext;
use WPGraphQL\Data\PostObjectMutation;
use WPGraphQL\Utils\Utils;

/**
 * Class PostObjectCreate
 *
 * @package WPGraphQL\Mutation
 */
class PostObjectCreate {
	/**
	 * Registers the PostObjectCreate mutation.
	 *
	 * @param \WP_Post_Type $post_type_object The post type of the mutation.
	 *
	 * @return void
	 */
	public static function register_mutation( WP_Post_Type $post_type_object ) {
		$mutation_name = 'create' . ucwords( $post_type_object->graphql_single_name );

		register_graphql_mutation(
			$mutation_name,
			[
				'inputFields'         => self::get_input_fields( $post_type_object ),
				'outputFields'        => self::get_output_fields( $post_type_object ),
				'mutateAndGetPayload' => self::mutate_and_get_payload( $post_type_object, $mutation_name ),
			]
		);
	}

	/**
	 * Defines the mutation input field configuration.
	 *
	 * @param \WP_Post_Type $post_type_object The post type of the mutation.
	 *
	 * @return array
	 */
	public static function get_input_fields( $post_type_object ) {
		$fields = [
			'date'      => [
				'type'        => 'String',
				'description' => __( 'The date of the object. Preferable to enter as year/month/day (e.g. 01/31/2017) as it will rearrange date as fit if it is not specified. Incomplete dates may have unintended results for example, "2017" as the input will use current date with timestamp 20:17 ', 'wp-graphql' ),
			],
			'menuOrder' => [
				'type'        => 'Int',
				'description' => __( 'A field used for ordering posts. This is typically used with nav menu items or for special ordering of hierarchical content types.', 'wp-graphql' ),
			],
			'password'  => [
				'type'        => 'String',
				'description' => __( 'The password used to protect the content of the object', 'wp-graphql' ),
			],
			'slug'      => [
				'type'        => 'String',
				'description' => __( 'The slug of the object', 'wp-graphql' ),
			],
			'status'    => [
				'type'        => 'PostStatusEnum',
				'description' => __( 'The status of the object', 'wp-graphql' ),
			],
		];

		if ( post_type_supports( $post_type_object->name, 'author' ) ) {
			$fields['authorId'] = [
				'type'        => 'ID',
				'description' => __( 'The userId to assign as the author of the object', 'wp-graphql' ),
			];
		}

		if ( post_type_supports( $post_type_object->name, 'comments' ) ) {
			$fields['commentStatus'] = [
				'type'        => 'String',
				'description' => __( 'The comment status for the object', 'wp-graphql' ),
			];
		}

		if ( post_type_supports( $post_type_object->name, 'editor' ) ) {
			$fields['content'] = [
				'type'        => 'String',
				'description' => __( 'The content of the object', 'wp-graphql' ),
			];
		}

		if ( post_type_supports( $post_type_object->name, 'excerpt' ) ) {
			$fields['excerpt'] = [
				'type'        => 'String',
				'description' => __( 'The excerpt of the object', 'wp-graphql' ),
			];
		}

		if ( post_type_supports( $post_type_object->name, 'title' ) ) {
			$fields['title'] = [
				'type'        => 'String',
				'description' => __( 'The title of the object', 'wp-graphql' ),
			];
		}

		if ( post_type_supports( $post_type_object->name, 'trackbacks' ) ) {
			$fields['pinged'] = [
				'type'        => [
					'list_of' => 'String',
				],
				'description' => __( 'URLs that have been pinged.', 'wp-graphql' ),
			];

			$fields['pingStatus'] = [
				'type'        => 'String',
				'description' => __( 'The ping status for the object', 'wp-graphql' ),
			];

			$fields['toPing'] = [
				'type'        => [
					'list_of' => 'String',
				],
				'description' => __( 'URLs queued to be pinged.', 'wp-graphql' ),
			];
		}

		if ( $post_type_object->hierarchical || in_array(
			$post_type_object->name,
			[
				'attachment',
				'revision',
			],
			true
		) ) {
			$fields['parentId'] = [
				'type'        => 'ID',
				'description' => __( 'The ID of the parent object', 'wp-graphql' ),
			];
		}

		if ( 'attachment' === $post_type_object->name ) {
			$fields['mimeType'] = [
				'type'        => 'MimeTypeEnum',
				'description' => __( 'If the post is an attachment or a media file, this field will carry the corresponding MIME type. This field is equivalent to the value of WP_Post->post_mime_type and the post_mime_type column in the "post_objects" database table.', 'wp-graphql' ),
			];
		}

		/** @var \WP_Taxonomy[] $allowed_taxonomies */
		$allowed_taxonomies = \WPGraphQL::get_allowed_taxonomies( 'objects' );

		foreach ( $allowed_taxonomies as $tax_object ) {
			// If the taxonomy is in the array of taxonomies registered to the post_type
			if ( in_array( $tax_object->name, get_object_taxonomies( $post_type_object->name ), true ) ) {
				$fields[ $tax_object->graphql_plural_name ] = [
					'description' => sprintf(
						// translators: %1$s is the post type GraphQL name, %2$s is the taxonomy GraphQL name.
						__( 'Set connections between the %1$s and %2$s', 'wp-graphql' ),
						$post_type_object->graphql_single_name,
						$tax_object->graphql_plural_name
					),
					'type'        => ucfirst( $post_type_object->graphql_single_name ) . ucfirst( $tax_object->graphql_plural_name ) . 'Input',
				];
			}
		}

		return $fields;
	}

	/**
	 * Defines the mutation output field configuration.
	 *
	 * @param \WP_Post_Type $post_type_object The post type of the mutation.
	 *
	 * @return array
	 */
	public static function get_output_fields( WP_Post_Type $post_type_object ) {
		return [
			$post_type_object->graphql_single_name => [
				'type'        => $post_type_object->graphql_single_name,
				'description' => __( 'The Post object mutation type.', 'wp-graphql' ),
				'resolve'     => static function ( $payload, $_args, AppContext $context ) {
					if ( empty( $payload['postObjectId'] ) || ! absint( $payload['postObjectId'] ) ) {
						return null;
					}

					return $context->get_loader( 'post' )->load_deferred( $payload['postObjectId'] );
				},
			],
		];
	}

	/**
	 * Defines the mutation data modification closure.
	 *
	 * @param \WP_Post_Type $post_type_object The post type of the mutation.
	 * @param string       $mutation_name    The mutation name.
	 *
	 * @return callable
	 */
	public static function mutate_and_get_payload( $post_type_object, $mutation_name ) {
		return static function ( $input, AppContext $context, ResolveInfo $info ) use ( $post_type_object, $mutation_name ) {

			/**
			 * Throw an exception if there's no input
			 */
			if ( ( empty( $post_type_object->name ) ) || ( empty( $input ) || ! is_array( $input ) ) ) {
				throw new UserError( esc_html__( 'Mutation not processed. There was no input for the mutation or the post_type_object was invalid', 'wp-graphql' ) );
			}

			/**
			 * Stop now if a user isn't allowed to create a post
			 */
			if ( ! isset( $post_type_object->cap->create_posts ) || ! current_user_can( $post_type_object->cap->create_posts ) ) {
				// translators: the $post_type_object->graphql_plural_name placeholder is the name of the object being mutated
				throw new UserError( esc_html( sprintf( __( 'Sorry, you are not allowed to create %1$s', 'wp-graphql' ), $post_type_object->graphql_plural_name ) ) );
			}

			/**
			 * If the post being created is being assigned to another user that's not the current user, make sure
			 * the current user has permission to edit others posts for this post_type
			 */
			if ( ! empty( $input['authorId'] ) ) {
				// Ensure authorId is a valid databaseId.
				$input['authorId'] = Utils::get_database_id_from_id( $input['authorId'] );

				$author = ! empty( $input['authorId'] ) ? get_user_by( 'ID', $input['authorId'] ) : false;

				if ( false === $author ) {
					throw new UserError( esc_html__( 'The provided `authorId` is not a valid user', 'wp-graphql' ) );
				}

				if ( get_current_user_id() !== $input['authorId'] && ( ! isset( $post_type_object->cap->edit_others_posts ) || ! current_user_can( $post_type_object->cap->edit_others_posts ) ) ) {
					// translators: the $post_type_object->graphql_plural_name placeholder is the name of the object being mutated
					throw new UserError( esc_html( sprintf( __( 'Sorry, you are not allowed to create %1$s as this user', 'wp-graphql' ), $post_type_object->graphql_plural_name ) ) );
				}
			}

			/**
			 * @todo: When we support assigning terms and setting posts as "sticky" we need to check permissions
			 * @see :https://github.com/WordPress/WordPress/blob/e357195ce303017d517aff944644a7a1232926f7/wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php#L504-L506
			 * @see : https://github.com/WordPress/WordPress/blob/e357195ce303017d517aff944644a7a1232926f7/wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php#L496-L498
			 */

			/**
			 * Insert the post object and get the ID
			 */
			$post_args = PostObjectMutation::prepare_post_object( $input, $post_type_object, $mutation_name );

			/**
			 * Filter the default post status to use when the post is initially created. Pass through a filter to
			 * allow other plugins to override the default (for example, Edit Flow, which provides control over
			 * customizing stati or various E-commerce plugins that make heavy use of custom stati)
			 *
			 * @param string       $default_status   The default status to be used when the post is initially inserted
			 * @param \WP_Post_Type $post_type_object The Post Type that is being inserted
			 * @param string       $mutation_name    The name of the mutation currently in progress
			 */
			$default_post_status = apply_filters( 'graphql_post_object_create_default_post_status', 'draft', $post_type_object, $mutation_name );

			/**
			 * We want to cache the "post_status" and set the status later. We will set the initial status
			 * of the inserted post as the default status for the site, allow side effects to process with the
			 * inserted post (set term object connections, set meta input, sideload images if necessary, etc)
			 * Then will follow up with setting the status as what it was declared to be later
			 */
			$intended_post_status = ! empty( $post_args['post_status'] ) ? $post_args['post_status'] : $default_post_status;

			/**
			 * If the current user cannot publish posts but their intent was to publish,
			 * default the status to pending.
			 */
			if ( ( ! isset( $post_type_object->cap->publish_posts ) || ! current_user_can( $post_type_object->cap->publish_posts ) ) && ! in_array(
				$intended_post_status,
				[
					'draft',
					'pending',
				],
				true
			) ) {
				$intended_post_status = 'pending';
			}

			/**
			 * Set the post_status as the default for the initial insert. The intended $post_status will be set after
			 * side effects are complete.
			 */
			$post_args['post_status'] = $default_post_status;

			$clean_args = wp_slash( (array) $post_args );

			if ( ! is_array( $clean_args ) || empty( $clean_args ) ) {
				throw new UserError( esc_html__( 'The object failed to create', 'wp-graphql' ) );
			}

			/**
			 * Insert the post and retrieve the ID
			 */
			$post_id = wp_insert_post( $clean_args, true );

			/**
			 * Throw an exception if the post failed to create
			 */
			if ( is_wp_error( $post_id ) ) {
				$error_message = $post_id->get_error_message();
				if ( ! empty( $error_message ) ) {
					throw new UserError( esc_html( $error_message ) );
				}

				throw new UserError( esc_html__( 'The object failed to create but no error was provided', 'wp-graphql' ) );
			}

			/**
			 * This updates additional data not part of the posts table (postmeta, terms, other relations, etc)
			 *
			 * The input for the postObjectMutation will be passed, along with the $new_post_id for the
			 * postObject that was created so that relations can be set, meta can be updated, etc.
			 */
			PostObjectMutation::update_additional_post_object_data( $post_id, $input, $post_type_object, $mutation_name, $context, $info, $default_post_status, $intended_post_status );

			/**
			 * Determine whether the intended status should be set or not.
			 *
			 * By filtering to false, the $intended_post_status will not be set at the completion of the mutation.
			 *
			 * This allows for side-effect actions to set the status later. For example, if a post
			 * was being created via a GraphQL Mutation, the post had additional required assets, such as images
			 * that needed to be sideloaded or some other semi-time-consuming side effect, those actions could
			 * be deferred (cron or whatever), and when those actions complete they could come back and set
			 * the $intended_status.
			 *
			 * @param boolean      $should_set_intended_status Whether to set the intended post_status or not. Default true.
			 * @param \WP_Post_Type $post_type_object The Post Type Object for the post being mutated
			 * @param string       $mutation_name              The name of the mutation currently in progress
			 * @param \WPGraphQL\AppContext $context The AppContext passed down to all resolvers
			 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo passed down to all resolvers
			 * @param string       $intended_post_status       The intended post_status the post should have according to the mutation input
			 * @param string       $default_post_status        The default status posts should use if an intended status wasn't set
			 */
			$should_set_intended_status = apply_filters( 'graphql_post_object_create_should_set_intended_post_status', true, $post_type_object, $mutation_name, $context, $info, $intended_post_status, $default_post_status );

			/**
			 * If the intended post status and the default post status are not the same,
			 * update the post with the intended status now that side effects are complete.
			 */
			if ( $intended_post_status !== $default_post_status && true === $should_set_intended_status ) {

				/**
				 * If the post was deleted by a side effect action before getting here,
				 * don't proceed.
				 */
				$new_post = get_post( $post_id );
				if ( empty( $new_post ) ) {
					throw new UserError( esc_html__( 'The status of the post could not be set', 'wp-graphql' ) );
				}

				/**
				 * If the $intended_post_status is different than the current status of the post
				 * proceed and update the status.
				 */
				if ( $intended_post_status !== $new_post->post_status ) {
					$update_args = [
						'ID'          => $post_id,
						'post_status' => $intended_post_status,
						// Prevent the post_date from being reset if the date was included in the create post $args
						// see: https://core.trac.wordpress.org/browser/tags/4.9/src/wp-includes/post.php#L3637
						'edit_date'   => ! empty( $post_args['post_date'] ) ? $post_args['post_date'] : false,
					];

					wp_update_post( $update_args );
				}
			}

			/**
			 * Return the post object
			 */
			return [
				'postObjectId' => $post_id,
			];
		};
	}
}
