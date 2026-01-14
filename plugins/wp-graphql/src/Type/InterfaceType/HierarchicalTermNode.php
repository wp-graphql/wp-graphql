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
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @throws \Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {
		register_graphql_interface_type(
			'HierarchicalTermNode',
			[
				'description' => static function () {
					return __( 'Term node with hierarchical (parent/child) relationships', 'wp-graphql' );
				},
				'interfaces'  => [
					'Node',
					'TermNode',
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
