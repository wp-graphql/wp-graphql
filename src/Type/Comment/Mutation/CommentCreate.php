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
class CommentCreate {

	/**
	 * Holds the mutation field definition
	 *
	 * @var array $mutation
	 */
	private static $mutation;

	/**
	 * Defines the create mutation for Comments
	 *
	 * @return array|mixed
	 */
	public static function mutate() {
		if ( empty( self::$mutation ) ) {
			$mutation_name  = 'CreateComment';
			self::$mutation = Relay::mutationWithClientMutationId( [
				'name'                => $mutation_name,
				'description'         => __( 'Create comment objects', 'wp-graphql' ),
				'inputFields'         => WPInputObjectType::prepare_fields( CommentMutation::input_fields(), $mutation_name ),
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

					/**
					 * Stop if post not open to comments
					 */
					if ( get_post( $input['postId'] )->post_status === 'closed' ) {
						throw new UserError( __( 'Sorry, this post is closed to comments at the moment', 'wp-graphql' ) );
					}

					/**
					 * Map all of the args from GraphQL to WordPress friendly args array
					 */
					$comment_args = [
						'comment_author_url' => '',
						'comment_type'       => '',
						'comment_parent'     => 0,
						'user_id'            => 0,
						'comment_author_IP'  => ':1',
						'comment_agent'      => '',
						'comment_date'       => date( 'Y-m-d H:i:s' ),
					];

					CommentMutation::prepare_comment_object( $input, $comment_args, $mutation_name );

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
							throw new UserError( __( 'The object failed to create but no error was provided', 'wp-graphql' ) );
						}
					}

					/**
					 * If the $comment_id is empty, we should throw an exception
					 */
					if ( empty( $comment_id ) ) {
						throw new UserError( __( 'The object failed to create', 'wp-graphql' ) );
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

				},
			] );
		}

		return ( ! empty( self::$mutation ) ) ? self::$mutation : null;
	}
}