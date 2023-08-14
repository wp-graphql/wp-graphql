<?php
namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class NodeWithPageAttributes {

	/**
	 * Registers the NodeWithPageAttributes Type to the Schema
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @return void
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'NodeWithPageAttributes',
			[
				'interfaces'  => [ 'Node' ],
				'description' => __( 'A node that can have page attributes', 'wp-graphql' ),
				'fields'      => [
					'menuOrder' => [
						'type'        => 'Int',
						'description' => __( 'A field used for ordering posts. This is typically used with nav menu items or for special ordering of hierarchical content types.', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
