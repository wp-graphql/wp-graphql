<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class NodeWithRevisions {
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'NodeWithRevisions',
			[
				'description' => __( 'A node that can have revisions', 'wp-graphql' ),
				'fields'      => [
					'isRevision' => [
						'type'        => 'Boolean',
						'description' => __( 'True if the node is a revision of another node', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
