<?php

namespace WPGraphQL\Type;

use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;

register_graphql_object_type( 'Comment', [
	'description' => __( 'A Comment object', 'wp-graphql' ),
	'interfaces'  => [ WPObjectType::node_interface() ],
	'fields'      => [
		'id'          => [
			'type'        => [
				'non_null' => 'ID'
			],
			'description' => __( 'The globally unique identifier for the user', 'wp-graphql' ),
			'resolve'     => function( \WP_Comment $comment, $args, $context, $info ) {
				return ! empty( $comment->comment_ID ) ? Relay::toGlobalId( 'comment', $comment->comment_ID ) : null;
			},
		],
		'commentId'   => [
			'type'        => 'Int',
			'description' => __( 'ID for the comment, unique among comments.', 'wp-graphql' ),
			'resolve'     => function( \WP_Comment $comment, $args, $context, $info ) {
				return ! empty( $comment->comment_ID ) ? $comment->comment_ID : 0;
			},
		],
		'commentedOn' => [
			'type'        => 'PostObjectUnion',
			'description' => __( 'The object the comment was added to', 'wp-graphql' ),
			'resolve'     => function( \WP_Comment $comment, $args, $context, $info ) {
				$post_object = null;
				if ( ! empty( $comment->comment_post_ID ) ) {
					$post_object = get_post( $comment->comment_post_ID );
					$post_object = isset( $post_object->post_type ) && isset( $post_object->ID ) ? DataSource::resolve_post_object( $post_object->ID, $post_object->post_type ) : null;
				}

				return $post_object;

			},
		],
		'author'      => [
			'type'        => 'CommentAuthorUnion',
			'description' => __( 'The author of the comment', 'wp-graphql' ),
			'resolve'     => function( \WP_Comment $comment, $args, $context, $info ) {
				/**
				 * If the comment has a user associated, use it to populate the author, otherwise return
				 * the $comment and the Union will use that to hydrate the CommentAuthor Type
				 */
				if ( ! empty( $comment->user_id ) ) {
					return DataSource::resolve_user( absint( $comment->user_id ) );
				} else {
					return DataSource::resolve_comment_author( $comment->comment_author_email );
				}
			},
		],
		'authorIp'    => [
			'type'        => 'String',
			'description' => __( 'IP address for the author. This field is equivalent to WP_Comment->comment_author_IP and the value matching the "comment_author_IP" column in SQL.', 'wp-graphql' ),
			'resolve'     => function( \WP_Comment $comment, $args, $context, $info ) {
				return ! empty( $comment->comment_author_IP ) ? $comment->comment_author_IP : '';
			},
		],
		'date'        => [
			'type'        => 'String',
			'description' => __( 'Date the comment was posted in local time. This field is equivalent to WP_Comment->date and the value matching the "date" column in SQL.', 'wp-graphql' ),
			'resolve'     => function( \WP_Comment $comment, $args, $context, $info ) {
				return ! empty( $comment->comment_date ) ? $comment->comment_date : '';
			},
		],
		'dateGmt'     => [
			'type'        => 'String',
			'description' => __( 'Date the comment was posted in GMT. This field is equivalent to WP_Comment->date_gmt and the value matching the "date_gmt" column in SQL.', 'wp-graphql' ),
			'resolve'     => function( \WP_Comment $comment, $args, $context, $info ) {
				return ! empty( $comment->comment_date_gmt ) ? $comment->comment_date_gmt : '';
			},
		],
		'content'     => [
			'type'        => 'String',
			'description' => __( 'Content of the comment. This field is equivalent to WP_Comment->comment_content and the value matching the "comment_content" column in SQL.', 'wp-graphql' ),
			'args'        => [
				'format' => [
					'type'        => 'PostObjectFieldFormatEnum',
					'description' => __( 'Format of the field output', 'wp-graphql' ),
				]
			],
			'resolve'     => function( \WP_Comment $comment, $args, $context, $info ) {
				$content = ! empty( $comment->comment_content ) ? $comment->comment_content : null;

				// If the raw format is requested, don't apply any filters.
				if ( isset( $args['format'] ) && 'raw' === $args['format'] ) {
					return $content;
				}

				return apply_filters( 'comment_text', $content );
			},
		],
		'karma'       => [
			'type'        => 'Int',
			'description' => __( 'Karma value for the comment. This field is equivalent to WP_Comment->comment_karma and the value matching the "comment_karma" column in SQL.', 'wp-graphql' ),
			'resolve'     => function( \WP_Comment $comment, $args, $context, $info ) {
				return ! empty( $comment->comment_karma ) ? $comment->comment_karma : 0;
			},
		],
		'approved'    => [
			'type'        => 'String',
			'description' => __( 'The approval status of the comment. This field is equivalent to WP_Comment->comment_approved and the value matching the "comment_approved" column in SQL.', 'wp-graphql' ),
			'resolve'     => function( \WP_Comment $comment, $args, $context, $info ) {
				return ! empty( $comment->comment_approved ) ? $comment->comment_approved : '';
			},
		],
		'agent'       => [
			'type'        => 'String',
			'description' => __( 'User agent used to post the comment. This field is equivalent to WP_Comment->comment_agent and the value matching the "comment_agent" column in SQL.', 'wp-graphql' ),
			'resolve'     => function( \WP_Comment $comment, $args, $context, $info ) {
				return ! empty( $comment->comment_agent ) ? $comment->comment_agent : '';
			},
		],
		'type'        => [
			'type'        => 'String',
			'description' => __( 'Type of comment. This field is equivalent to WP_Comment->comment_type and the value matching the "comment_type" column in SQL.', 'wp-graphql' ),
			'resolve'     => function( \WP_Comment $comment, $args, $context, $info ) {
				return ! empty( $comment->comment_type ) ? $comment->comment_type : '';
			},
		],
		'parent'      => [
			'type'        => 'Comment',
			'description' => __( 'Parent comment of current comment. This field is equivalent to the WP_Comment instance matching the WP_Comment->comment_parent ID.', 'wp-graphql' ),
			'resolve'     => function( \WP_Comment $comment, $args, $context, $info ) {
				return get_comment( $comment->comment_parent );
			},
		],
	]
] );
