<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

/**
 * Class HierarchicalTermNode
 *
 * @package WPGraphQL\Type\InterfaceType
 */
class HierarchicalTermNode {

	/**
	 * Register the HierarchicalTermNode Interface Type
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {

		register_graphql_interface_type( 'HierarchicalTermNode', [
			'description' => __( 'Term node with hierarchical (parent/child) relationships', 'wp-graphql' ),
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
		]);

	}

}
