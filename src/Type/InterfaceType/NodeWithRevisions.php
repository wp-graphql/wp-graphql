<?php
namespace WPGraphQL\Type\InterfaceType;

use Exception;
use WPGraphQL\Registry\TypeRegistry;

class NodeWithRevisions {

	/**
	 * Registers the NodeWithRevisions Type to the Schema
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'NodeWithRevisions',
			[
				'description' => __( 'A node that can have revisions', 'wp-graphql' ),
				'interfaces'  => [ 'Node', 'ContentNode', 'DatabaseIdentifier' ],
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
