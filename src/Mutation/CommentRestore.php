<?php

namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Utils\Utils;

/**
 * Class CommentRestore
 *
 * @package WPGraphQL\Mutation
 */
class CommentRestore {
	/**
	 * Registers the CommentRestore mutation.
	 *
	 * @return void
	 */
	public static function register_mutation() {
		register_graphql_mutation(
			'restoreComment',
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
			'id' => [
				'type'        => [
					'non_null' => 'ID',
				],
				'description' => __( 'The ID of the comment to be restored', 'wp-graphql' ),
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
			'restoredId' => [
				'type'        => 'Id',
				'description' => __( 'The ID of the restored comment', 'wp-graphql' ),
				'resolve'     => function ( $payload ) {
					$restore = (object) $payload['commentObject'];

					return ! empty( $restore->comment_ID ) ? Relay::toGlobalId( 'comment', $restore->comment_ID ) : null;
				},
			],
			'comment'    => [
				'type'        => 'Comment',
				'description' => __( 'The restored comment object', 'wp-graphql' ),
				'resolve'     => function ( $payload, $args, AppContext $context ) {
					if ( ! isset( $payload['commentObject']->comment_ID ) || ! absint( $payload['commentObject']->comment_ID ) ) {
						return null;
					}
					return $context->get_loader( 'comment' )->load_deferred( absint( $payload['commentObject']->comment_ID ) );
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
		return function ( $input ) {
			// Stop now if a user isn't allowed to delete the comment.
			if ( ! current_user_can( 'moderate_comments' ) ) {
				throw new UserError( __( 'Sorry, you are not allowed to restore this comment.', 'wp-graphql' ) );
			}

			// Get the database ID for the comment.
			$comment_id = Utils::get_database_id_from_id( $input['id'] );

			if ( false === $comment_id ) {
				throw new UserError( __( 'Sorry, you are not allowed to restore this comment.', 'wp-graphql' ) );
			}

			// Delete the comment.
			wp_untrash_comment( $comment_id );

			$comment = get_comment( $comment_id );

			return [
				'commentObject' => $comment,
			];
		};
	}
}
