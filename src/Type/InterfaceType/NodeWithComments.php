<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class NodeWithComments {
	/**
	 * Registers the NodeWithComments Type to the Schema
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'NodeWithComments',
			[
				'description' => __( 'A node that can have comments associated with it', 'wp-graphql' ),
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
