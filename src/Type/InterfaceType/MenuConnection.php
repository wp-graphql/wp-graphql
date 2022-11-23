<?php

namespace WPGraphQL\Type\InterfaceType;

use Exception;
use WPGraphQL\Registry\TypeRegistry;

class MenuConnection {

	/**
	 * Register the MenuConnection Interface
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {

		register_graphql_interface_type( 'MenuConnection', [
			'interfaces'  => [ 'Connection' ],
			'description' => __( 'Connection to Menu Nodes', 'wp-graphql' ),
			'fields'      => [
				'edges' => [
					'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'MenuConnectionEdge' ] ] ],
					'description' => __( 'A list of edges (relational context) between connected nodes', 'wp-graphql' ),
				],
				'nodes' => [
					'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'Menu' ] ] ],
					'description' => __( 'A list of connected Menu Nodes', 'wp-graphql' ),
				],
			],
		] );

		register_graphql_interface_type( 'MenuConnectionEdge', [
			'interfaces'  => [ 'Edge' ],
			'description' => __( 'Edge between a Node and a connected Menu Node', 'wp-graphql' ),
			'fields'      => [
				'type'        => [ 'non_null' => 'Menu' ],
				'description' => __( 'The connected Menu Node', 'wp-graphql' ),
			],
		]);

	}

}
