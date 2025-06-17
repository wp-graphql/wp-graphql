<?php

namespace WPGraphQL\Type\InterfaceType;

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
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @throws \Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {
		register_graphql_interface_type(
			'HierarchicalNode',
			[
				'description' => static function () {
					return __( 'Content that can exist in a parent-child structure. Provides fields for navigating up (parent) and down (children) through the hierarchy.', 'wp-graphql' );
				},
				'interfaces'  => [
					'Node',
					'DatabaseIdentifier',
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
