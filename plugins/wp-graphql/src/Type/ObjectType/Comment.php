<?php

namespace WPGraphQL\Type\ObjectType;

use WPGraphQL\AppContext;
use WPGraphQL\Model\Comment as CommentModel;

/**
 * Class Comment
 *
 * @package WPGraphQL\Type\Object
 */
class Comment {

	/**
	 * Register Comment Type
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_object_type(
			'Comment',
			[
				'description' => static function () {
					return __( 'A response or reaction to content submitted by users. Comments are typically associated with a specific content entry.', 'wp-graphql' );
				},
				'model'       => CommentModel::class,
				'interfaces'  => [ 'Node', 'DatabaseIdentifier', 'UniformResourceIdentifiable' ],
				'connections' => [
					'author' => [
						'toType'      => 'Commenter',
						'description' => static function () {
							return __( 'The author of the comment', 'wp-graphql' );
						},
						'oneToOne'    => true,
						'edgeFields'  => [
							'email'     => [
								'type'        => 'String',
								'description' => static function () {
									return __( 'Email address representing the author for this particular comment', 'wp-graphql' );
								},
								'resolve'     => static function ( $edge ) {
									return $edge['source']->commentAuthorEmail ?: null;
								},
							],
							'ipAddress' => [
								'type'        => 'String',
								'description' => static function () {
									return __( 'IP address of the author at the time of making this comment.', 'wp-graphql' );
								},
								'resolve'     => static function ( $edge ) {
									return $edge['source']->authorIp ?: null;
								},
							],
							'name'      => [
								'type'        => 'String',
								'description' => static function () {
									return __( 'The display name of the comment author for this particular comment', 'wp-graphql' );
								},
								'resolve'     => static function ( $edge ) {
									return $edge['source']->commentAuthor;
								},
							],
							'url'       => [
								'type'        => 'String',
								'description' => static function () {
									return __( 'The url entered for the comment author on this particular comment', 'wp-graphql' );
								},
								'resolve'     => static function ( $edge ) {
									return $edge['source']->commentAuthorUrl ?: null;
								},
							],
						],
						'resolve'     => static function ( $comment, $_args, AppContext $context ) {
							$node = null;

							// try and load the user node
							if ( ! empty( $comment->userId ) ) {
								$node = $context->get_loader( 'user' )->load( absint( $comment->userId ) );
							}

							// If no node is loaded, fallback to the
							// public comment author data
							if ( ! $node || ( true === $node->isPrivate ) ) {
								$node = ! empty( $comment->commentId ) ? $context->get_loader( 'comment_author' )->load( $comment->commentId ) : null;
							}

							return [
								'node'   => $node,
								'source' => $comment,
							];
						},
					],
				],
				'fields'      => static function () {
					return [
						'agent'            => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'User agent (browser or client) used to post the comment.', 'wp-graphql' );
							},
						],
						'approved'         => [
							'type'              => 'Boolean',
							'description'       => static function () {
								return __( 'The approval status of the comment.', 'wp-graphql' );
							},
							'deprecationReason' => __( 'Deprecated in favor of the `status` field', 'wp-graphql' ),
							'resolve'           => static function ( $comment ) {
								return 'approve' === $comment->status;
							},
						],
						'authorIp'         => [
							'type'              => 'String',
							'deprecationReason' => __( 'Use the ipAddress field on the edge between the comment and author', 'wp-graphql' ),
							'description'       => static function () {
								return __( 'IP address for the author at the time of commenting.', 'wp-graphql' );
							},
						],
						'commentId'        => [
							'type'              => 'Int',
							'description'       => static function () {
								return __( 'ID for the comment, unique among comments.', 'wp-graphql' );
							},
							'deprecationReason' => static function () {
								return __( 'Deprecated in favor of databaseId', 'wp-graphql' );
							},
						],
						'content'          => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Content of the comment.', 'wp-graphql' );
							},
							'args'        => [
								'format' => [
									'type'        => 'PostObjectFieldFormatEnum',
									'description' => static function () {
										return __( 'Format of the field output', 'wp-graphql' );
									},
								],
							],
							'resolve'     => static function ( \WPGraphQL\Model\Comment $comment, $args ) {
								if ( isset( $args['format'] ) && 'raw' === $args['format'] ) {
									return isset( $comment->contentRaw ) ? $comment->contentRaw : null;
								} else {
									return isset( $comment->contentRendered ) ? $comment->contentRendered : null;
								}
							},
						],
						'date'             => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Date the comment was posted in local time.', 'wp-graphql' );
							},
						],
						'dateGmt'          => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Date the comment was posted in GMT.', 'wp-graphql' );
							},
						],
						'id'               => [
							'description' => static function () {
								return __( 'The globally unique identifier for the comment object', 'wp-graphql' );
							},
						],
						'isRestricted'     => [
							'type'        => 'Boolean',
							'description' => static function () {
								return __( 'Whether the object is restricted from the current viewer', 'wp-graphql' );
							},
						],
						'karma'            => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'Karma value for the comment.', 'wp-graphql' );
							},
						],
						'link'             => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'The permalink of the comment', 'wp-graphql' );
							},
						],
						'parentId'         => [
							'type'        => 'ID',
							'description' => static function () {
								return __( 'The globally unique identifier of the parent comment node.', 'wp-graphql' );
							},
						],
						'parentDatabaseId' => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'The database id of the parent comment node or null if it is the root comment', 'wp-graphql' );
							},
						],
						'status'           => [
							'type'        => 'CommentStatusEnum',
							'description' => static function () {
								return __( 'The approval status of the comment.', 'wp-graphql' );
							},
						],
						'type'             => [
							'type'        => 'String',
							'description' => static function () {
								return __( 'Type of comment.', 'wp-graphql' );
							},
						],
					];
				},
			]
		);
	}
}
