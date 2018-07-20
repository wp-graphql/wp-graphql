<?php

namespace WPGraphQL\Type\Comment\Mutation;

use GraphQL\Error\UserError;
use GraphQLRelay\Relay;
use WPGraphQL\Types;

/**
 * Class CommentDelete
 *
 * @package WPGraphQL\Type\Comment\Mutation
 */
class CommentDelete {

	/**
	 * Holds the mutation field definition
	 *
	 * @var array $mutation
	 */
	private static $mutation = [];

	/**
	 * Defines the delete mutation for Comments
	 *
	 * @return array|mixed
	 */
	public static function mutate() {
		if ( empty( self::$mutation ) ) {
			/**
			 * Set the name of the mutation being performed
			 */
			$mutation_name  = 'DeleteComment';
			self::$mutation = Relay::mutationWithClientMutationId( [
				'name'                => $mutation_name,
				'description'         => __( 'Delete comment objects', 'wp-graphql' ),
				'inputFields'         => [
					'id'          => [
						'type'        => Types::non_null( Types::id() ),
						'description' => __( 'The ID of the comment to be deleted', 'wp-graphql' ),
					],
					'forceDelete' => [
						'type'        => Types::boolean(),
						'description' => __( 'Whether the comment should be force deleted instead of being moved to the trash', 'wp-graphql' ),
					],
				],
				'outputFields'        => [
					'deletedId' => [
						'type'        => Types::id(),
						'description' => __( 'The ID of the deleted comment', 'wp-graphql' ),
						'resolve'     => function ( $payload ) {
							$deleted = ( object ) $payload['commentObject'];

							return ! empty( $deleted->comment_ID ) ? Relay::toGlobalId( 'comment', absint( $deleted->comment_ID ) ) : null;
						},
					],
					'comment'   => [
						'type'        => Types::comment(),
						'description' => __( 'The comment before it was deleted', 'wp-graphql' ),
						'resolve'     => function ( $payload ) {
							$deleted = ( object ) $payload['commentObject'];

							return ! empty( $deleted ) ? $deleted : null;
						},
					],
				],
				'mutateAndGetPayload' => function ( $input ) {
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
					if (
						! current_user_can( 'moderate_comments' ) &&
						absint( get_current_user_id() ) !== absint( $user_id )
					) {
						throw new UserError( __( 'Sorry, you are not allowed to delete this comment.', 'wp-graphql' ) );
					}

					/**
					 * Check if we should force delete or not
					 */
					$force_delete = ( ! empty( $input['forceDelete'] ) && true === $input['forceDelete'] ) ? true : false;

					/**
					 * Delete the comment
					 */
					$deleted = wp_delete_comment( $id_parts['id'], $force_delete );

					return [
						'commentObject' => $comment_before_delete,
					];
				}
			] );
		}

		return ( ! empty( self::$mutation ) ) ? self::$mutation : null;
	}
}