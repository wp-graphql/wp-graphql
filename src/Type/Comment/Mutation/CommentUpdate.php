<?php

namespace WPGraphQL\Type\Comment\Mutation;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Type\WPInputObjectType;
use WPGraphQL\Types;

/**
 * Class CommentCreate
 *
 * @package WPGraphQL\Type\Comment\Mutation
 */
class CommentUpdate {

	/**
	 * Holds the mutation field definition
	 *
	 * @var array $mutation
	 */
	private static $mutation = [];

	/**
	 * Defines the create mutation for Comments
	 *
	 * @return array|mixed
	 */
	public static function mutate() {
		if ( empty( self::$mutation ) ) {
			$mutation_name  = 'UpdateComment';
			self::$mutation = Relay::mutationWithClientMutationId( [
				'name'                => $mutation_name,
				'description'         => __( 'Create comment objects', 'wp-graphql' ),
				'inputFields'         => WPInputObjectType::prepare_fields(
					array_merge(
						[
							'id' => [
								'type'        => Types::non_null( Types::id() ),
								'description' => __( 'The ID of the comment being updated.', 'wp-graphql' ),
							],
						],
						CommentMutation::input_fields()
					), $mutation_name ),
				'outputFields'        => [
					'comment' => [
						'type'    => Types::comment(),
						'resolve' => function ( $payload ) {
							return get_comment( $payload['id'] );
						},
					],
				],
				'mutateAndGetPayload' => function ( $input, AppContext $context, ResolveInfo $info ) use ( $mutation_name ) {
					/**
					 * Throw an exception if there's no input
					 */
					if ( ( empty( $input ) || ! is_array( $input ) ) ) {
						throw new UserError( __( 'Mutation not processed. There was no input for the mutation or the comment_object was invalid', 'wp-graphql' ) );
					}

					$id_parts     = ! empty( $input['id'] ) ? Relay::fromGlobalId( $input['id'] ) : null;
					$comment_id   = absint( $id_parts['id'] );
					$comment_args = get_comment( $comment_id, ARRAY_A );


					/**
					 * Map all of the args from GraphQL to WordPress friendly args array
					 */
					$user_id = $comment_args['user_id'];
					CommentMutation::prepare_comment_object( $input, $comment_args, $mutation_name, true );

					/**
					 * Check if use has required capabilities
					 */
					if (
						! current_user_can( 'moderate_comments' ) &&
						absint( get_current_user_id() ) !== absint( $user_id )
					) {
						throw new UserError( __( 'You do not have the appropriate capabilities to update this comment.', 'wp-graphql' ) );
					}

					/**
					 * Update comment
					 * $success   int   1 on success and 0 on fail
					 */
					$success = wp_update_comment( $comment_args );

					/**
					 * Throw an exception if the comment failed to be created
					 */
					if ( ! $success ) {
						throw new UserError( __( 'The comment failed to update', 'wp-graphql' ) );
					}

					/**
					 * This updates additional data not part of the comments table ( commentmeta, other relations, etc )
					 *
					 * The input for the commentMutation will be passed, along with the $new_comment_id for the
					 * comment that was created so that relations can be set, meta can be updated, etc.
					 */
					CommentMutation::update_additional_comment_data( $comment_id, $input, 'create', $context, $info );

					/**
					 * Return the comment object
					 */
					return [ 'id' => $comment_id ];
				},
			] );
		}

		return ( ! empty( self::$mutation ) ) ? self::$mutation : null;
	}
}