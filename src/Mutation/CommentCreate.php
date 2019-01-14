<?php

namespace WPGraphQL\Mutation;

use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\CommentMutation;

class CommentCreate {
    /**
     * Registers the CommentCreate mutation.
     */
    public static function register_mutation() {
        register_graphql_mutation( 'createComment', [
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
            'postId'      => [
                'type'        => 'Int',
                'description' => __( 'The ID of the post the comment belongs to.', 'wp-graphql' ),
            ],
            'userId'      => [
                'type'        => 'Int',
                'description' => __( 'The userID of the comment\'s author.', 'wp-graphql' ),
            ],
            'author'      => [
                'type'        => 'String',
                'description' => __( 'The name of the comment\'s author.', 'wp-graphql' ),
            ],
            'authorEmail' => [
                'type'        => 'String',
                'description' => __( 'The email of the comment\'s author.', 'wp-graphql' ),
            ],
            'authorUrl'   => [
                'type'        => 'String',
                'description' => __( 'The url of the comment\'s author.', 'wp-graphql' ),
            ],
            'authorIp'    => [
                'type'        => 'String',
                'description' => __( 'IP address for the comment\'s author.', 'wp-graphql' ),
            ],
            'content'     => [
                'type'        => 'String',
                'description' => __( 'Content of the comment.', 'wp-graphql' ),
            ],
            'type'        => [
                'type'        => 'String',
                'description' => __( 'Type of comment.', 'wp-graphql' ),
            ],
            'parent'      => [
                'type'        => 'ID',
                'description' => __( 'Parent comment of current comment.', 'wp-graphql' ),
            ],
            'agent'       => [
                'type'        => 'String',
                'description' => __( 'User agent used to post the comment.', 'wp-graphql' ),
            ],
            'date'        => [
                'type'        => 'String',
                'description' => __( 'The date of the object. Preferable to enter as year/month/day ( e.g. 01/31/2017 ) as it will rearrange date as fit if it is not specified. Incomplete dates may have unintended results for example, "2017" as the input will use current date with timestamp 20:17 ', 'wp-graphql' ),
            ],
            'approved'    => [
                'type'        => 'String',
                'description' => __( 'The approval status of the comment.', 'wp-graphql' ),
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
            'comment' => [
                'type'        => 'Comment',
                'description' => __( 'The comment that was created', 'wp-graphql' ),
                'resolve'     => function ( $payload ) {
                    return get_comment( $payload['id'] );
                },
            ]
        ];
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

            CommentMutation::prepare_comment_object( $input, $comment_args, 'createComment' );

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
            CommentMutation::update_additional_comment_data( $comment_id, $input, 'createComment', $context, $info );

            /**
             * Return the comment object
             */
            return [
                'id' => $comment_id,
            ];
        };
    }
}