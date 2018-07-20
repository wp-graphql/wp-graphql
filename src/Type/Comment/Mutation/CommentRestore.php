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
class CommentRestore {

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
			$mutation_name  = 'UntrashComment';
			self::$mutation = Relay::mutationWithClientMutationId( [
				'name'                => $mutation_name,
				'description'         => __( 'Restore comment objects from trash', 'wp-graphql' ),
				'inputFields'         => [
					'id' => [
						'type'        => Types::non_null( Types::id() ),
						'description' => __( 'The ID of the comment to be restored', 'wp-graphql' ),
					],
				],
				'outputFields'        => [
					'restoredId' => [
						'type'        => Types::id(),
						'description' => __( 'The ID of the restored comment', 'wp-graphql' ),
						'resolve'     => function ( $payload ) {
							$restore = ( object ) $payload['commentObject'];

							return ! empty( $restore->comment_ID ) ? Relay::toGlobalId( 'comment', absint( $restore->comment_ID ) ) : null;
						},
					],
					'comment'    => [
						'type'        => Types::comment(),
						'description' => __( 'The restored comment object', 'wp-graphql' ),
						'resolve'     => function ( $payload ) {
							$restore = ( object ) $payload['commentObject'];

							return ! empty( $restore ) ? $restore : null;
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
					$comment_id = absint( $id_parts['id'] );

					/**
					 * Stop now if a user isn't allowed to delete the comment
					 */
					if ( ! current_user_can( 'moderate_comments' ) ) {
						throw new UserError( __( 'Sorry, you are not allowed to delete this comment.', 'wp-graphql' ) );
					}

					/**
					 * Delete the comment
					 */
					$restored = wp_untrash_comment( $id_parts['id'] );

					$comment = get_comment( $comment_id );

					return [
						'commentObject' => $comment,
					];
				}
			] );
		}

		return ( ! empty( self::$mutation ) ) ? self::$mutation : null;
	}
}