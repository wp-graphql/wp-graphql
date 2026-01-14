<?php

namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\CommentMutation;
use WPGraphQL\Utils\Utils;

/**
 * Class CommentUpdate
 *
 * @package WPGraphQL\Mutation
 */
class CommentUpdate {
	/**
	 * Registers the CommentUpdate mutation.
	 *
	 * @return void
	 * @throws \Exception
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
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_input_fields() {
		return array_merge(
			CommentCreate::get_input_fields(),
			[
				'id' => [
					'type'        => [
						'non_null' => 'ID',
					],
					'description' => static function () {
						return __( 'The ID of the comment being updated.', 'wp-graphql' );
					},
				],
			]
		);
	}

	/**
	 * Defines the mutation output field configuration.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_output_fields() {
		return CommentCreate::get_output_fields();
	}

	/**
	 * Defines the mutation data modification closure.
	 *
	 * @return callable(array<string,mixed>$input,\WPGraphQL\AppContext $context,\GraphQL\Type\Definition\ResolveInfo $info):array<string,mixed>
	 */
	public static function mutate_and_get_payload() {
		return static function ( $input, AppContext $context, ResolveInfo $info ) {
			// Get the database ID for the comment.
			$comment_id = ! empty( $input['id'] ) ? Utils::get_database_id_from_id( $input['id'] ) : null;

			// Get the args from the existing comment.
			$comment_args = ! empty( $comment_id ) ? get_comment( $comment_id, ARRAY_A ) : null;

			if ( empty( $comment_id ) || empty( $comment_args ) ) {
				throw new UserError( esc_html__( 'The Comment could not be updated', 'wp-graphql' ) );
			}

			/**
			 * Map all of the args from GraphQL to WordPress friendly args array
			 */
			$user_id          = $comment_args['user_id'] ?? null;
			$raw_comment_args = $comment_args;
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
				throw new UserError( esc_html__( 'Sorry, you are not allowed to update this comment.', 'wp-graphql' ) );
			}

			// If there are no changes between the existing comment and the incoming comment
			if ( $comment_args === $raw_comment_args ) {
				throw new UserError( esc_html__( 'No changes have been provided to the comment.', 'wp-graphql' ) );
			}

			/**
			 * Update comment
			 * $success   int   1 on success and 0 on fail
			 */
			$success = wp_update_comment( $comment_args, true );

			/**
			 * Throw an exception if the comment failed to be created
			 */
			if ( is_wp_error( $success ) ) {
				throw new UserError( esc_html( $success->get_error_message() ) );
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
				'id'      => $comment_id,
				'success' => (bool) $success,
			];
		};
	}
}
