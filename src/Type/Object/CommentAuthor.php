<?php

namespace WPGraphQL\Type;

use GraphQLRelay\Relay;

register_graphql_object_type( 'CommentAuthor', [
	'description' => __( 'A Comment Author object', 'wp-graphql' ),
	'interfaces'  => [ WPObjectType::node_interface() ],
	'fields'      => [
		'id'    => [
			'type'        => [
				'non_null' => 'ID',
			],
			'description' => __( 'The globally unique identifier for the Comment Author user', 'wp-graphql' ),
			'resolve'     => function( array $comment_author, $args, $context, $info ) {
				return ! empty( $comment_author['comment_author_email'] ) ? Relay::toGlobalId( 'commentAuthor', $comment_author['comment_author_email'] ) : null;
			},
		],
		'name'  => [
			'type'        => 'String',
			'description' => __( 'The name for the comment author.', 'wp-graphql' ),
			'resolve'     => function( array $comment_author, $args, $context, $info ) {
				return ! empty( $comment_author['comment_author'] ) ? $comment_author['comment_author'] : '';
			},
		],
		'email' => [
			'type'        => 'String',
			'description' => __( 'The email for the comment author', 'wp-graphql' ),
			'resolve'     => function( array $comment_author, $args, $context, $info ) {
				return ! empty( $comment_author['comment_author_email'] ) ? $comment_author['comment_author_email'] : '';
			},
		],
		'url'   => [
			'type'        => 'String',
			'description' => __( 'The url the comment author.', 'wp-graphql' ),
			'resolve'     => function( array $comment_author, $args, $context, $info ) {
				return ! empty( $comment_author['comment_author_url'] ) ? $comment_author['comment_author_url'] : '';
			},
		],
	],
] );
