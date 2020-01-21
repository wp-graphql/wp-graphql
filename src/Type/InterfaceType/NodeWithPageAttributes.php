<?php
namespace WPGraphQL\Type\InterfaceType;

class NodeWithPageAttributes {
	public static function register_type() {
		register_graphql_interface_type( 'NodeWithPageAttributes', [
			'description' => __( 'A node that can have page attributes', 'wp-graphql' ),
			'fields' => [
				'menuOrder'     => [
					'type'        => 'Int',
					'description' => __( 'A field used for ordering posts. This is typically used with nav menu items or for special ordering of hierarchical content types.', 'wp-graphql' ),
				],
			],
		]);
	}
}
