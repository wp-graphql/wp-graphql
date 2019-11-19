<?php

namespace WPGraphQL\Type\Object;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Data\DataSource;

class Comment {
	public static function register_type() {
		register_graphql_object_type(
			'Comment',
			[
				'description' => __( 'A Comment object', 'wp-graphql' ),
				'interfaces'  => [ 'Node' ],
				'fields'      => [
					'id'           => [
						'description' => __( 'The globally unique identifier for the comment object', 'wp-graphql' ),
					],
					'commentId'    => [
						'type'        => 'Int',
						'description' => __( 'ID for the comment, unique among comments.', 'wp-graphql' ),
					],
					'commentedOn'  => [
						'type'        => 'PostObjectUnion',
						'description' => __( 'The object the comment was added to', 'wp-graphql' ),
						'resolve'     => function( \WPGraphQL\Model\Comment $comment, $args, AppContext $context, ResolveInfo $info ) {
							if ( empty( $comment->comment_post_ID ) || ! absint( $comment->comment_post_ID ) ) {
								return null;
							}
							$id = absint( $comment->comment_post_ID );

							return DataSource::resolve_post_object( $id, $context );
						},
					],
					'author'       => [
						'type'        => 'CommentAuthorUnion',
						'description' => __( 'The author of the comment', 'wp-graphql' ),
						'resolve'     => function( \WPGraphQL\Model\Comment $comment, $args, AppContext $context, ResolveInfo $info ) {

							/**
							 * If the comment has a user associated, use it to populate the author, otherwise return
							 * the $comment and the Union will use that to hydrate the CommentAuthor Type
							 */
							if ( ! empty( $comment->userId ) ) {
								if ( empty( $comment->userId ) || ! absint( $comment->userId ) ) {
									return null;
								}

								return DataSource::resolve_user( $comment->userId, $context );

							} else {
								return ! empty( $comment->commentId ) ? DataSource::resolve_comment_author( $comment->commentId ) : null;
							}
						},
					],
					'authorIp'     => [
						'type'        => 'String',
						'description' => __( 'IP address for the author. This field is equivalent to WP_Comment->comment_author_IP and the value matching the "comment_author_IP" column in SQL.', 'wp-graphql' ),
					],
					'date'         => [
						'type'        => 'String',
						'description' => __( 'Date the comment was posted in local time. This field is equivalent to WP_Comment->date and the value matching the "date" column in SQL.', 'wp-graphql' ),
					],
					'dateGmt'      => [
						'type'        => 'String',
						'description' => __( 'Date the comment was posted in GMT. This field is equivalent to WP_Comment->date_gmt and the value matching the "date_gmt" column in SQL.', 'wp-graphql' ),
					],
					'content'      => [
						'type'        => 'String',
						'description' => __( 'Content of the comment. This field is equivalent to WP_Comment->comment_content and the value matching the "comment_content" column in SQL.', 'wp-graphql' ),
						'args'        => [
							'format' => [
								'type'        => 'PostObjectFieldFormatEnum',
								'description' => __( 'Format of the field output', 'wp-graphql' ),
							],
						],
						'resolve'     => function( \WPGraphQL\Model\Comment $comment, $args ) {
							if ( isset( $args['format'] ) && 'raw' === $args['format'] ) {
								return $comment->contentRaw;
							} else {
								return $comment->contentRendered;
							}
						},
					],
					'karma'        => [
						'type'        => 'Int',
						'description' => __( 'Karma value for the comment. This field is equivalent to WP_Comment->comment_karma and the value matching the "comment_karma" column in SQL.', 'wp-graphql' ),
					],
					'approved'     => [
						'type'        => 'Boolean',
						'description' => __( 'The approval status of the comment. This field is equivalent to WP_Comment->comment_approved and the value matching the "comment_approved" column in SQL.', 'wp-graphql' ),
					],
					'agent'        => [
						'type'        => 'String',
						'description' => __( 'User agent used to post the comment. This field is equivalent to WP_Comment->comment_agent and the value matching the "comment_agent" column in SQL.', 'wp-graphql' ),
					],
					'type'         => [
						'type'        => 'String',
						'description' => __( 'Type of comment. This field is equivalent to WP_Comment->comment_type and the value matching the "comment_type" column in SQL.', 'wp-graphql' ),
					],
					'parent'       => [
						'type'        => 'Comment',
						'description' => __( 'Parent comment of current comment. This field is equivalent to the WP_Comment instance matching the WP_Comment->comment_parent ID.', 'wp-graphql' ),
						'resolve'     => function( \WPGraphQL\Model\Comment $comment, $args, AppContext $context, ResolveInfo $info ) {
							return ! empty( $comment->comment_parent_id ) ? DataSource::resolve_comment( $comment->comment_parent_id, $context ) : null;
						},
					],
					'isRestricted' => [
						'type'        => 'Boolean',
						'description' => __( 'Whether the object is restricted from the current viewer', 'wp-graphql' ),
					],
				],
			]
		);

	}
}
