<?php
namespace WPGraphQL\Type\InterfaceType;

use Exception;
use WPGraphQL\Registry\TypeRegistry;

class NodeWithPageAttributes {

	/**
	 * Registers the NodeWithPageAttributes Type to the Schema
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ) {
		register_graphql_interface_type(
			'NodeWithPageAttributes',
			[
				'description' => __( 'A node that can have page attributes', 'wp-graphql' ),
				'interfaces'  => [ 'Node', 'ContentNode', 'DatabaseIdentifier' ],
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
