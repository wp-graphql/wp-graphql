<?php

namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Model\Comment;

class CommentDelete {
	/**
	 * Registers the CommentDelete mutation.
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
	 * @return array
	 */
	public static function get_input_fields() {
		return [
			'id'          => [
				'type'        => [
					'non_null' => 'ID',
				],
				'description' => __( 'The deleted comment ID', 'wp-graphql' ),
			],
			'forceDelete' => [
				'type'        => 'Boolean',
				'description' => __( 'Whether the comment should be force deleted instead of being moved to the trash', 'wp-graphql' ),
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
			'deletedId' => [
				'type'        => 'Id',
				'description' => __( 'The deleted comment ID', 'wp-graphql' ),
				'resolve'     => function( $payload ) {
					$deleted = (object) $payload['commentObject'];

					return ! empty( $deleted->comment_ID ) ? Relay::toGlobalId( 'comment', absint( $deleted->comment_ID ) ) : null;
				},
			],
			'comment'   => [
				'type'        => 'Comment',
				'description' => __( 'The deleted comment object', 'wp-graphql' ),
				'resolve'     => function( $payload, $args, AppContext $context, ResolveInfo $info ) {
					return $payload['commentObject'] ? $payload['commentObject'] : null;
				},
			],
		];
	}

	/**
	 * Defines the mutation data modification closure.
	 *
	 * @return callable
	 */
	public static function mutate_and_get_payload() {
		return function( $input ) {
			/**
			 * Get the ID from the global ID
			 */
			$id_parts = Relay::fromGlobalId( $input['id'] );

			/**
			 * Get the post object before deleting it
			 */
			$comment_id            = absint( $id_parts['id'] );
			$comment_before_delete = get_comment( $comment_id );

			/**
			 * Stop now if a user isn't allowed to delete the comment
			 */
			$user_id = $comment_before_delete->user_id;

			// Prevent comment deletions by default
			$not_allowed = true;

			// If the current user can moderate comments proceed
			if ( current_user_can( 'moderate_comments' ) ) {
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
				throw new UserError( __( 'Sorry, you are not allowed to delete this comment.', 'wp-graphql' ) );
			}

			/**
			 * Check if we should force delete or not
			 */
			$force_delete = ( ! empty( $input['forceDelete'] ) && true === $input['forceDelete'] ) ? true : false;

			$comment_before_delete = new Comment( $comment_before_delete );

			/**
			 * Delete the comment
			 */
			wp_delete_comment( $id_parts['id'], $force_delete );

			return [
				'commentObject' => $comment_before_delete,
			];
		};
	}
}
