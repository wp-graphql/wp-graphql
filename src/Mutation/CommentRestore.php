<?php

namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;
use GraphQLRelay\Relay;

class CommentRestore {
    /**
     * Registers the CommentRestore mutation.
     */
    public static function register_mutation() {
        register_graphql_mutation( 'restoreComment', [
            'inputFields'         => self::get_input_fields(),
            'outputFields'        => self::get_output_fields(),
            'mutateAndGetPayload' => self::mutate_and_get_payload(),
        ] );
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
                    $restore = ( object ) $payload['commentObject'];

                    return ! empty( $restore->comment_ID ) ? Relay::toGlobalId( 'comment', absint( $restore->comment_ID ) ) : null;
                },
            ],
            'comment'    => [
                'type'        => 'Comment',
                'description' => __( 'The restored comment object', 'wp-graphql' ),
                'resolve'     => function ( $payload ) {
                    $restore = ( object ) $payload['commentObject'];

                    return ! empty( $restore ) ? $restore : null;
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
            wp_untrash_comment( $id_parts['id'] );

            $comment = get_comment( $comment_id );

            return [
                'commentObject' => $comment,
            ];
        };
    }
}