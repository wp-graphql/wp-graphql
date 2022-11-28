<?php

namespace WPGraphQL\Type\InterfaceType;

use Exception;
use WPGraphQL\Registry\TypeRegistry;

class MenuItemConnection {

	/**
	 * Register the MenuItemConnection Interface
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {

		register_graphql_interface_type( 'MenuItemConnection', [
			'interfaces'  => [ 'Connection' ],
			'description' => __( 'Connection to Menu Item Nodes', 'wp-graphql' ),
			'fields'      => [
				'edges' => [
					'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'MenuItemConnectionEdge' ] ] ],
					'description' => __( 'A list of edges (relational context) between connected nodes', 'wp-graphql' ),
				],
				'nodes' => [
					'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'MenuItem' ] ] ],
					'description' => __( 'A list of connected Menu Item Nodes', 'wp-graphql' ),
				],
			],
		] );

		register_graphql_interface_type( 'MenuItemConnectionEdge', [
			'interfaces'  => [ 'Edge' ],
			'description' => __( 'Edge between a Node and a connected Menu Item Node', 'wp-graphql' ),
			'fields'      => [
				'type'        => [ 'non_null' => 'MenuItem' ],
				'description' => __( 'The connected Menu Item Node', 'wp-graphql' ),
			],
		]);

	}

}
