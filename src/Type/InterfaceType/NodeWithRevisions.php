<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Model\Post;

class NodeWithRevisions {
	public static function register_type() {
		register_graphql_interface_type( 'NodeWithRevisions', [
			'description' => __( 'A node that can have revisions', 'wp-graphql' ),
			'fields' => [
				'isRevision'    => [
					'type'        => 'Boolean',
					'description' => __( 'Whether the object is a revision', 'wp-graphql' ),
					'resolve'     => function( Post $post, $args, $context, $info ) {
						return 'revision' === $post->post_type ? true : false;
					},
				],
			],
		]);
	}
}
