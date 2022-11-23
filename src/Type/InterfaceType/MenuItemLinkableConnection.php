<?php

namespace WPGraphQL\Type\InterfaceType;

use Exception;
use WPGraphQL\Registry\TypeRegistry;

class MenuItemLinkableConnection {

	/**
	 * Register the MenuItemLinkableConnection Interface
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {

		register_graphql_interface_type( 'MenuItemLinkableConnection', [
			'interfaces'  => [ 'Connection' ],
			'description' => __( 'Connection to Menu Item Linkable Nodes', 'wp-graphql' ),
			'fields'      => [
				'edges' => [
					'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'MenuItemLinkableConnectionEdge' ] ] ],
					'description' => __( 'A list of edges (relational context) between connected nodes', 'wp-graphql' ),
				],
				'nodes' => [
					'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'MenuItemLinkable' ] ] ],
					'description' => __( 'A list of connected Menu Item Linkable Nodes', 'wp-graphql' ),
				],
			],
		] );

		register_graphql_interface_type( 'MenuItemLinkableConnectionEdge', [
			'interfaces'  => [ 'Edge' ],
			'description' => __( 'Edge between a Node and a connected Menu Item Linkable Node', 'wp-graphql' ),
			'fields'      => [
				'type'        => [ 'non_null' => 'MenuItemLinkable' ],
				'description' => __( 'The connected Menu Item Linkable Node', 'wp-graphql' ),
			],
		]);

	}

}
