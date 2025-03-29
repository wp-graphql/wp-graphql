<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class NodeWithComments {
	/**
	 * Registers the NodeWithComments Type to the Schema
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'NodeWithComments',
			[
				'interfaces'  => [ 'Node' ],
				'description' => __( 'Content that can receive and display user-submitted comments. Provides fields for accessing comment counts and managing comment status.', 'wp-graphql' ),
				'fields'      => [
					'commentCount'  => [
						'type'        => 'Int',
						'description' => __( 'The number of comments. Even though WPGraphQL denotes this field as an integer, in WordPress this field should be saved as a numeric string for compatibility.', 'wp-graphql' ),
					],
					'commentStatus' => [
						'type'        => 'String',
						'description' => __( 'Whether the comments are open or closed for this particular post.', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
