<?php

namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;
use GraphQLRelay\Relay;
use WPGraphQL\Model\Comment;
use WPGraphQL\Utils\Utils;

class CommentDelete {
	/**
	 * Registers the CommentDelete mutation.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function register_mutation() {
		register_graphql_mutation(
			'deleteComment',
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
			'id'          => [
				'type'        => [
					'non_null' => 'ID',
				],
				'description' => static function () {
					return __( 'The deleted comment ID', 'wp-graphql' );
				},
			],
			'forceDelete' => [
				'type'        => 'Boolean',
				'description' => static function () {
					return __( 'Whether the comment should be force deleted instead of being moved to the trash', 'wp-graphql' );
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
			'deletedId' => [
				'type'        => 'Id',
				'description' => static function () {
					return __( 'The deleted comment ID', 'wp-graphql' );
				},
				'resolve'     => static function ( $payload ) {
					$deleted = (object) $payload['commentObject'];

					return ! empty( $deleted->comment_ID ) ? Relay::toGlobalId( 'comment', $deleted->comment_ID ) : null;
				},
			],
			'comment'   => [
				'type'        => 'Comment',
				'description' => static function () {
					return __( 'The deleted comment object', 'wp-graphql' );
				},
				'resolve'     => static function ( $payload ) {
					return $payload['commentObject'] ? $payload['commentObject'] : null;
				},
			],
		];
	}

	/**
	 * Defines the mutation data modification closure.
	 *
	 * @return callable(array<string,mixed>$input,\WPGraphQL\AppContext $context,\GraphQL\Type\Definition\ResolveInfo $info):array<string,mixed>
	 */
	public static function mutate_and_get_payload() {
		return static function ( $input ) {
			/**
			 * A caller who is not logged in can neither moderate comments nor own one, so they can
			 * never pass the check below. Rejecting them before the comment is looked up means they
			 * get the same answer for every ID and cannot use the mutation to find out which
			 * comment IDs exist, including unapproved ones.
			 *
			 * @see https://github.com/wp-graphql/wp-graphql/security/advisories/GHSA-r7m4-3v6c-3435
			 */
			if ( ! is_user_logged_in() ) {
				throw new UserError( esc_html__( 'Sorry, you are not allowed to delete this comment.', 'wp-graphql' ) );
			}

			$can_moderate = current_user_can( 'moderate_comments' );

			// Get the database ID for the comment.
			$comment_id = Utils::get_database_id_from_id( $input['id'] );

			// Get the post object before deleting it.
			$comment_before_delete = ! empty( $comment_id ) ? get_comment( $comment_id ) : false;

			if ( empty( $comment_before_delete ) ) {
				/**
				 * Someone who may moderate comments is entitled to know that an ID holds nothing,
				 * and the message is more useful to them than a refusal would be.
				 *
				 * Everyone else gets the same answer here as they would for a comment that exists
				 * but is not theirs. Ownership cannot be checked before the lookup, the way the
				 * capability can for the media item mutations, so the two outcomes are made
				 * identical instead. Otherwise the pair of messages tells any logged-in caller
				 * which comment IDs exist, including unapproved ones.
				 *
				 * @see https://github.com/wp-graphql/wp-graphql/security/advisories/GHSA-r7m4-3v6c-3435
				 */
				throw new UserError(
					$can_moderate
						? esc_html__( 'The Comment could not be deleted', 'wp-graphql' )
						: esc_html__( 'Sorry, you are not allowed to delete this comment.', 'wp-graphql' )
				);
			}

			/**
			 * Stop now if a user isn't allowed to delete the comment
			 */
			$user_id = $comment_before_delete->user_id;

			// Prevent comment deletions by default
			$not_allowed = true;

			// If the current user can moderate comments proceed
			if ( $can_moderate ) {
				$not_allowed = false;
			} else {
				// Get the current user id
				$current_user_id = absint( get_current_user_id() );
				// If the current user ID is the same as the comment author's ID, then the
				// current user is the comment author and can delete the comment
				if ( 0 !== $current_user_id && absint( $user_id ) === $current_user_id ) {
					$not_allowed = false;
				}
			}

			/**
			 * If the mutation has been prevented
			 */
			if ( true === $not_allowed ) {
				throw new UserError( esc_html__( 'Sorry, you are not allowed to delete this comment.', 'wp-graphql' ) );
			}

			/**
			 * Check if we should force delete or not
			 */
			$force_delete = ! empty( $input['forceDelete'] ) && true === $input['forceDelete'];

			$comment_before_delete = new Comment( $comment_before_delete );

			/**
			 * Delete the comment
			 */
			wp_delete_comment( (int) $comment_id, $force_delete );

			return [
				'commentObject' => $comment_before_delete,
			];
		};
	}
}
