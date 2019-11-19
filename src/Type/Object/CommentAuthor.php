<?php

namespace WPGraphQL\Type\Object;

class CommentAuthor {
	public static function register_type() {
		register_graphql_object_type(
			'CommentAuthor',
			[
				'description' => __( 'A Comment Author object', 'wp-graphql' ),
				'interfaces'  => [ 'Node' ],
				'fields'      => [
					'id'           => [
						'description' => __( 'The globally unique identifier for the comment author object', 'wp-graphql' ),
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
	}
}
