<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class NodeWithRevisions {

	/**
	 * Registers the NodeWithRevisions Type to the Schema
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'NodeWithRevisions',
			[
				'interfaces'  => [ 'Node' ],
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
