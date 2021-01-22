<?php

namespace WPGraphQL\Type\Object;

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
				'description' => __( 'A Comment object', 'wp-graphql' ),
				'interfaces'  => [ 'Node', 'DatabaseIdentifier' ],
				'fields'      => [
					'id'               => [
						'description' => __( 'The globally unique identifier for the comment object', 'wp-graphql' ),
					],
					'commentId'        => [
						'type'              => 'Int',
						'description'       => __( 'ID for the comment, unique among comments.', 'wp-graphql' ),
						'deprecationReason' => __( 'Deprecated in favor of databaseId', 'wp-graphql' ),
					],
					'authorIp'         => [
						'type'        => 'String',
						'description' => __( 'IP address for the author. This field is equivalent to WP_Comment->comment_author_IP and the value matching the "comment_author_IP" column in SQL.', 'wp-graphql' ),
					],
					'date'             => [
						'type'        => 'String',
						'description' => __( 'Date the comment was posted in local time. This field is equivalent to WP_Comment->date and the value matching the "date" column in SQL.', 'wp-graphql' ),
					],
					'dateGmt'          => [
						'type'        => 'String',
						'description' => __( 'Date the comment was posted in GMT. This field is equivalent to WP_Comment->date_gmt and the value matching the "date_gmt" column in SQL.', 'wp-graphql' ),
					],
					'content'          => [
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
								return isset( $comment->contentRaw ) ? $comment->contentRaw : null;
							} else {
								return isset( $comment->contentRendered ) ? $comment->contentRendered : null;
							}
						},
					],
					'karma'            => [
						'type'        => 'Int',
						'description' => __( 'Karma value for the comment. This field is equivalent to WP_Comment->comment_karma and the value matching the "comment_karma" column in SQL.', 'wp-graphql' ),
					],
					'approved'         => [
						'type'        => 'Boolean',
						'description' => __( 'The approval status of the comment. This field is equivalent to WP_Comment->comment_approved and the value matching the "comment_approved" column in SQL.', 'wp-graphql' ),
					],
					'agent'            => [
						'type'        => 'String',
						'description' => __( 'User agent used to post the comment. This field is equivalent to WP_Comment->comment_agent and the value matching the "comment_agent" column in SQL.', 'wp-graphql' ),
					],
					'type'             => [
						'type'        => 'String',
						'description' => __( 'Type of comment. This field is equivalent to WP_Comment->comment_type and the value matching the "comment_type" column in SQL.', 'wp-graphql' ),
					],
					'isRestricted'     => [
						'type'        => 'Boolean',
						'description' => __( 'Whether the object is restricted from the current viewer', 'wp-graphql' ),
					],
					'parentId'         => [
						'type'        => 'ID',
						'description' => __( 'The globally unique identifier of the parent comment node.', 'wp-graphql' ),
					],
					'parentDatabaseId' => [
						'type'        => 'Int',
						'description' => __( 'The database id of the parent comment node or null if it is the root comment', 'wp-graphql' ),
					],
				],
			]
		);

	}
}
