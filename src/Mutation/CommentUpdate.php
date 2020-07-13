<?php

namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLRelay\Relay;
use WPGraphQL\AppContext;
use WPGraphQL\Data\CommentMutation;

/**
 * Class CommentUpdate
 *
 * @package WPGraphQL\Mutation
 */
class CommentUpdate {
	/**
	 * Registers the CommentUpdate mutation.
	 */
	public static function register_mutation() {
		register_graphql_mutation(
			'updateComment',
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
		return array_merge(
			CommentCreate::get_input_fields(),
			[
				'id' => [
					'type'        => [
						'non_null' => 'ID',
					],
					'description' => __( 'The ID of the comment being updated.', 'wp-graphql' ),
				],
			]
		);
	}

	/**
	 * Defines the mutation output field configuration.
	 *
	 * @return array
	 */
	public static function get_output_fields() {
		return CommentCreate::get_output_fields();
	}

	/**
	 * Defines the mutation data modification closure.
	 *
	 * @return callable
	 */
	public static function mutate_and_get_payload() {
		return function ( $input, AppContext $context, ResolveInfo $info ) {
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
			CommentMutation::prepare_comment_object( $input, $comment_args, 'update', true );

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
				throw new UserError( __( 'Sorry, you are not allowed to update this comment.', 'wp-graphql' ) );
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
			return [
				'id' => $comment_id,
			];
		};
	}
}
