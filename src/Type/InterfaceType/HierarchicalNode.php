<?php

namespace WPGraphQL\Type\InterfaceType;

use Exception;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class HierarchicalNode
 *
 * @package WPGraphQL\Type\InterfaceType
 */
class HierarchicalNode {

	/**
	 * Register the HierarchicalNode Interface Type
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ):void {

		register_graphql_interface_type(
			'HierarchicalNode',
			[
				'description' => __( 'Node with hierarchical (parent/child) relationships', 'wp-graphql' ),
				'interfaces'  => [
					'Node',
					'DatabaseIdentifier',
				],
				'fields'      => [
					'parentId'         => [
						'type'        => 'ID',
						'description' => __( 'The globally unique identifier of the parent node.', 'wp-graphql' ),
					],
					'parentDatabaseId' => [
						'type'        => 'Int',
						'description' => __( 'Database id of the parent node', 'wp-graphql' ),
					],
				],
			]
		);

	}

}
