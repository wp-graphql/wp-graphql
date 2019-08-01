<?php

namespace WPGraphQL\Type;

register_graphql_object_type(
	'CommentAuthor',
	[
		'description' => __( 'A Comment Author object', 'wp-graphql' ),
		'interfaces'  => [ WPObjectType::node_interface() ],
		'fields'      => [
			'id'           => [
				'type'        => [
					'non_null' => 'ID',
				],
				'description' => __( 'The globally unique identifier for the Comment Author user', 'wp-graphql' ),
			],
			'name'         => [
				'type'        => 'String',
				'description' => __( 'The name for the comment author.', 'wp-graphql' ),
			],
			'email'        => [
				'type'        => 'String',
				'description' => __( 'The email for the comment author', 'wp-graphql' ),
			],
			'url'          => [
				'type'        => 'String',
				'description' => __( 'The url the comment author.', 'wp-graphql' ),
			],
			'isRestricted' => [
				'type'        => 'Boolean',
				'description' => __( 'Whether the object is restricted from the current viewer', 'wp-graphql' ),
			],
		],
	]
);
