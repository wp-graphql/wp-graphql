<?php
namespace WPGraphQL\Type\InterfaceType;

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
	 * @throws \Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {
		register_graphql_interface_type(
			'HierarchicalContentNode',
			[
				'description' => static function () {
					return __( 'Content that can be organized in a parent-child structure. Provides fields for navigating up and down the hierarchy and maintaining structured relationships.', 'wp-graphql' );
				},
				'interfaces'  => [
					'Node',
					'ContentNode',
					'DatabaseIdentifier',
					'HierarchicalNode',
				],
				'fields'      => static function () {
					return [
						'parentId'         => [
							'type'        => 'ID',
							'description' => static function () {
								return __( 'The globally unique identifier of the parent node.', 'wp-graphql' );
							},
						],
						'parentDatabaseId' => [
							'type'        => 'Int',
							'description' => static function () {
								return __( 'Database id of the parent node', 'wp-graphql' );
							},
						],
					];
				},
			]
		);
	}
}
