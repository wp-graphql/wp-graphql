<?php
namespace WPGraphQL\Type\InterfaceType;

use Exception;
use WPGraphQL\Registry\TypeRegistry;

/**
 * Class HierarchicalContentNode
 *
 * @package WPGraphQL\Type\InterfaceType
 */
class HierarchicalContentNode {

	/**
	 * Register the HierarchicalContentNode Interface Type
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {
		register_graphql_interface_type(
			'HierarchicalContentNode',
			[
				'description' => __( 'Content node with hierarchical (parent/child) relationships', 'wp-graphql' ),
				'interfaces'  => [
					'Node',
					'ContentNode',
					'DatabaseIdentifier',
					'HierarchicalNode',
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
