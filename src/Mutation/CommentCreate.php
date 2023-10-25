<?php

namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\CommentMutation;

class CommentCreate {
	/**
	 * Registers the CommentCreate mutation.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function register_mutation() {
		register_graphql_mutation(
			'createComment',
			[
				'inputFields'         => self::get_input_fields(),
				'outputFields'        => self::get_output_fields(),
				'mutateAndGetPayload' => self::mutate_and_get_payload(),
			]
		);
	}

	/**
	 * Defines the mutation input field configuration.
	 *
	 * @return array
	 */
	public static function get_input_fields() {
		return [
			'approved'    => [
				'type'              => 'String',
				'description'       => __( 'The approval status of the comment.', 'wp-graphql' ),
				'deprecationReason' => __( 'Deprecated in favor of the status field', 'wp-graphql' ),
			],
			'author'      => [
				'type'        => 'String',
				'description' => __( 'The name of the comment\'s author.', 'wp-graphql' ),
			],
			'authorEmail' => [
				'type'        => 'String',
				'description' => __( 'The email of the comment\'s author.', 'wp-graphql' ),
			],
			'authorUrl'   => [
				'type'        => 'String',
				'description' => __( 'The url of the comment\'s author.', 'wp-graphql' ),
			],
			'commentOn'   => [
				'type'        => 'Int',
				'description' => __( 'The database ID of the post object the comment belongs to.', 'wp-graphql' ),
			],
			'content'     => [
				'type'        => 'String',
				'description' => __( 'Content of the comment.', 'wp-graphql' ),
			],
			'date'        => [
				'type'        => 'String',
				'description' => __( 'The date of the object. Preferable to enter as year/month/day ( e.g. 01/31/2017 ) as it will rearrange date as fit if it is not specified. Incomplete dates may have unintended results for example, "2017" as the input will use current date with timestamp 20:17 ', 'wp-graphql' ),
			],
			'parent'      => [
				'type'        => 'ID',
				'description' => __( 'Parent comment ID of current comment.', 'wp-graphql' ),
			],
			'status'      => [
				'type'        => 'CommentStatusEnum',
				'description' => __( 'The approval status of the comment', 'wp-graphql' ),
			],
			'type'        => [
				'type'        => 'String',
				'description' => __( 'Type of comment.', 'wp-graphql' ),
			],
		];
	}

	/**
	 * Defines the mutation output field configuration.
	 *
	 * @return array
	 */
	public static function get_output_fields() {
		return [
			'comment' => [
				'type'        => 'Comment',
				'description' => __( 'The comment that was created', 'wp-graphql' ),
				'resolve'     => static function ( $payload, $args, AppContext $context ) {
					if ( ! isset( $payload['id'] ) || ! absint( $payload['id'] ) ) {
						return null;
					}

					return $context->get_loader( 'comment' )->load_deferred( absint( $payload['id'] ) );
				},
			],
			/**
			 * Comments can be created by non-authenticated users, but if the comment is not approved
			 * the user will not have access to the comment in response to the mutation.
			 *
			 * This field allows for the mutation to respond with a success message that the
			 * comment was indeed created, even if it cannot be returned in the response to respect
			 * server privacy.
			 *
			 * If the success comes back as true, the client can then use that response to
			 * dictate if they should use the input values as an optimistic response to the mutation
			 * and store in the cache, localStorage, cookie or whatever else so that the
			 * client can see their comment while it's still pending approval.
			 */
			'success' => [
				'type'        => 'Boolean',
				'description' => __( 'Whether the mutation succeeded. If the comment is not approved, the server will not return the comment to a non authenticated user, but a success message can be returned if the create succeeded, and the client can optimistically add the comment to the client cache', 'wp-graphql' ),
			],
		];
	}

	/**
	 * Defines the mutation data modification closure.
	 *
	 * @return callable
	 */
	public static function mutate_and_get_payload() {
		return static function ( $input, AppContext $context, ResolveInfo $info ) {

			/**
			 * Throw an exception if there's no input
			 */
			if ( ( empty( $input ) || ! is_array( $input ) ) ) {
				throw new UserError( esc_html__( 'Mutation not processed. There was no input for the mutation or the comment_object was invalid', 'wp-graphql' ) );
			}

			$commented_on = get_post( absint( $input['commentOn'] ) );

			if ( empty( $commented_on ) ) {
				throw new UserError( esc_html__( 'The ID of the node to comment on is invalid', 'wp-graphql' ) );
			}

			/**
			 * Stop if post not open to comments
			 */
			if ( empty( $input['commentOn'] ) || 'closed' === $commented_on->comment_status ) {
				throw new UserError( esc_html__( 'Sorry, this post is closed to comments at the moment', 'wp-graphql' ) );
			}

			if ( '1' === get_option( 'comment_registration' ) && ! is_user_logged_in() ) {
				throw new UserError( esc_html__( 'This site requires you to be logged in to leave a comment', 'wp-graphql' ) );
			}

			/**
			 * Map all of the args from GraphQL to WordPress friendly args array
			 */
			$comment_args = [
				'comment_author_url' => '',
				'comment_type'       => '',
				'comment_parent'     => 0,
				'user_id'            => 0,
				'comment_date'       => gmdate( 'Y-m-d H:i:s' ),
			];

			CommentMutation::prepare_comment_object( $input, $comment_args, 'createComment' );

			/**
			 * Insert the comment and retrieve the ID
			 */
			$comment_id = wp_new_comment( $comment_args, true );

			/**
			 * Throw an exception if the comment failed to be created
			 */
			if ( is_wp_error( $comment_id ) ) {
				$error_message = $comment_id->get_error_message();
				if ( ! empty( $error_message ) ) {
					throw new UserError( esc_html( $error_message ) );
				} else {
					throw new UserError( esc_html__( 'The object failed to create but no error was provided', 'wp-graphql' ) );
				}
			}

			/**
			 * If the $comment_id is empty, we should throw an exception
			 */
			if ( empty( $comment_id ) ) {
				throw new UserError( esc_html__( 'The object failed to create', 'wp-graphql' ) );
			}

			/**
			 * This updates additional data not part of the comments table ( commentmeta, other relations, etc )
			 *
			 * The input for the commentMutation will be passed, along with the $new_comment_id for the
			 * comment that was created so that relations can be set, meta can be updated, etc.
			 */
			CommentMutation::update_additional_comment_data( $comment_id, $input, 'createComment', $context, $info );

			/**
			 * Return the comment object
			 */
			return [
				'id'      => $comment_id,
				'success' => true,
			];
		};
	}
}
