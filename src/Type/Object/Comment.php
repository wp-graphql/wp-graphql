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
		],
		'commentId'   => [
			'type'        => 'Int',
			'description' => __( 'ID for the comment, unique among comments.', 'wp-graphql' ),
		],
		'commentedOn' => [
			'type'        => 'PostObjectUnion',
			'description' => __( 'The object the comment was added to', 'wp-graphql' ),
		],
		'author'      => [
			'type'        => 'CommentAuthorUnion',
			'description' => __( 'The author of the comment', 'wp-graphql' ),
		],
		'authorIp'    => [
			'type'        => 'String',
			'description' => __( 'IP address for the author. This field is equivalent to WP_Comment->comment_author_IP and the value matching the "comment_author_IP" column in SQL.', 'wp-graphql' ),
		],
		'date'        => [
			'type'        => 'String',
			'description' => __( 'Date the comment was posted in local time. This field is equivalent to WP_Comment->date and the value matching the "date" column in SQL.', 'wp-graphql' ),
		],
		'dateGmt'     => [
			'type'        => 'String',
			'description' => __( 'Date the comment was posted in GMT. This field is equivalent to WP_Comment->date_gmt and the value matching the "date_gmt" column in SQL.', 'wp-graphql' ),
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
			'resolve'     => function( $comment, $args ) {
				if ( isset( $args['format'] ) && 'raw' === $args['format'] ) {
					return $comment->contentRaw;
				} else {
					return $comment->contentRendered;
				}
			},
		],
		'karma'       => [
			'type'        => 'Int',
			'description' => __( 'Karma value for the comment. This field is equivalent to WP_Comment->comment_karma and the value matching the "comment_karma" column in SQL.', 'wp-graphql' ),
		],
		'approved'    => [
			'type'        => 'String',
			'description' => __( 'The approval status of the comment. This field is equivalent to WP_Comment->comment_approved and the value matching the "comment_approved" column in SQL.', 'wp-graphql' ),
		],
		'agent'       => [
			'type'        => 'String',
			'description' => __( 'User agent used to post the comment. This field is equivalent to WP_Comment->comment_agent and the value matching the "comment_agent" column in SQL.', 'wp-graphql' ),
		],
		'type'        => [
			'type'        => 'String',
			'description' => __( 'Type of comment. This field is equivalent to WP_Comment->comment_type and the value matching the "comment_type" column in SQL.', 'wp-graphql' ),
		],
		'parent'      => [
			'type'        => 'Comment',
			'description' => __( 'Parent comment of current comment. This field is equivalent to the WP_Comment instance matching the WP_Comment->comment_parent ID.', 'wp-graphql' ),
		],
	]
] );
