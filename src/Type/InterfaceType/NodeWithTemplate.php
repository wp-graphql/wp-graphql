<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class NodeWithTemplate {
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type( 'NodeWithTemplate', [
			'description' => __( 'A node that can have a template associated with it', 'wp-graphql' ),
			'fields'      => [
				'template' => [
					'description' => __( 'The template assigned to the node', 'wp-graphql' ),
					'type'        => 'ContentTemplate',
				],
			],
		]);
	}
}
