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
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_input_fields() {
		return [
			'approved'    => [
				'type'              => 'String',
				'description'       => static function () {
					return __( 'The approval status of the comment.', 'wp-graphql' );
				},
				'deprecationReason' => static function () {
					return __( 'Deprecated in favor of the status field', 'wp-graphql' );
				},
			],
			'author'      => [
				'type'        => 'String',
				'description' => static function () {
					return __( 'The name of the comment\'s author.', 'wp-graphql' );
				},
			],
			'authorEmail' => [
				'type'        => 'String',
				'description' => static function () {
					return __( 'The email of the comment\'s author.', 'wp-graphql' );
				},
			],
			'authorUrl'   => [
				'type'        => 'String',
				'description' => static function () {
					return __( 'The url of the comment\'s author.', 'wp-graphql' );
				},
			],
			'commentOn'   => [
				'type'        => 'Int',
				'description' => static function () {
					return __( 'The database ID of the post object the comment belongs to.', 'wp-graphql' );
				},
			],
			'content'     => [
				'type'        => 'String',
				'description' => static function () {
					return __( 'Content of the comment.', 'wp-graphql' );
				},
			],
			'date'        => [
				'type'        => 'String',
				'description' => static function () {
					return __( 'The date of the object. Preferable to enter as year/month/day ( e.g. 01/31/2017 ) as it will rearrange date as fit if it is not specified. Incomplete dates may have unintended results for example, "2017" as the input will use current date with timestamp 20:17 ', 'wp-graphql' );
				},
			],
			'parent'      => [
				'type'        => 'ID',
				'description' => static function () {
					return __( 'Parent comment ID of current comment.', 'wp-graphql' );
				},
			],
			'status'      => [
				'type'        => 'CommentStatusEnum',
				'description' => static function () {
					return __( 'The approval status of the comment', 'wp-graphql' );
				},
			],
			'type'        => [
				'type'        => 'String',
				'description' => static function () {
					return __( 'Type of comment.', 'wp-graphql' );
				},
			],
		];
	}

	/**
	 * Defines the mutation output field configuration.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_output_fields() {
		return [
			'comment' => [
				'type'        => 'Comment',
				'description' => static function () {
					return __( 'The comment that was created', 'wp-graphql' );
				},
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
				'description' => static function () {
					return __( 'Whether the mutation succeeded. If the comment is not approved, the server will not return the comment to a non authenticated user, but a success message can be returned if the create succeeded, and the client can optimistically add the comment to the client cache', 'wp-graphql' );
				},
			],
		];
	}

	/**
	 * Stops the mutation unless the current user may comment on the target.
	 *
	 * Mirrors the conditions WordPress applies in
	 * `wp_handle_comment_submission()`: a trashed post, a status that is neither
	 * public nor private, a private post the user cannot read, and a
	 * password-protected post all refuse the comment, as does a post with comments
	 * closed.
	 *
	 * It also mirrors how core reports them. Core returns a `WP_Error` carrying no
	 * message for the cases the caller is not entitled to tell apart, and a real
	 * message only where the caller can already read the post. Doing otherwise
	 * turns the mutation into a way to discover which IDs exist and what state
	 * they are in, which is the point of this guard as much as refusing the write
	 * is.
	 *
	 * @param \WP_Post|null $commented_on The post being commented on.
	 * @param int           $post_id      The ID that was asked for.
	 *
	 * @throws \GraphQL\Error\UserError If the comment must not be created.
	 */
	protected static function assert_can_comment_on( ?\WP_Post $commented_on, int $post_id ): void {
		// One message for everything the caller must not be able to tell apart: a
		// post that does not exist, one in the trash, one they cannot read, and one
		// behind a password all look the same from outside.
		$not_available = static function (): void {
			throw new UserError( esc_html__( 'The ID of the node to comment on is invalid', 'wp-graphql' ) );
		};

		if ( ! $commented_on instanceof \WP_Post || empty( $commented_on->comment_status ) ) {
			$not_available();
		}

		// get_post_status() resolves an attachment to its parent's status, the same
		// as core does here.
		$status     = get_post_status( $commented_on );
		$status_obj = get_post_status_object( (string) $status );

		if ( 'private' === $status && ! current_user_can( 'read_post', $post_id ) ) {
			$not_available();
		}

		if ( 'trash' === $status ) {
			$not_available();
		}

		if ( null === $status_obj || ( ! $status_obj->public && ! $status_obj->private ) ) {
			// A draft or pending post. Someone who can already read it is told why;
			// everyone else cannot learn that it exists.
			if ( current_user_can( 'read_post', $post_id ) ) {
				throw new UserError( esc_html__( 'Sorry, comments are not allowed for this item', 'wp-graphql' ) );
			}

			$not_available();
		}

		if ( post_password_required( $post_id ) ) {
			$not_available();
		}

		// Reached only for a post the caller can read, so saying comments are closed
		// discloses nothing they could not already see.
		if ( ! comments_open( $post_id ) ) {
			throw new UserError( esc_html__( 'Sorry, this post is closed to comments at the moment', 'wp-graphql' ) );
		}
	}

	/**
	 * Defines the mutation data modification closure.
	 *
	 * @return callable(array<string,mixed>$input,\WPGraphQL\AppContext $context,\GraphQL\Type\Definition\ResolveInfo $info):array<string,mixed>
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

			self::assert_can_comment_on( $commented_on, absint( $input['commentOn'] ) );

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
