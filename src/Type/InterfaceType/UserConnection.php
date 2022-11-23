<?php

namespace WPGraphQL\Type\InterfaceType;

use Exception;
use WPGraphQL\Registry\TypeRegistry;

class UserConnection {

	/**
	 * Register the UserConnection Interface
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {

		register_graphql_interface_type( 'UserConnection', [
			'interfaces'  => [ 'Connection' ],
			'description' => __( 'Connection to User Nodes', 'wp-graphql' ),
			'fields'      => [
				'edges' => [
					'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'UserConnectionEdge' ] ] ],
					'description' => __( 'A list of edges (relational context) between connected nodes', 'wp-graphql' ),
				],
				'nodes' => [
					'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'User' ] ] ],
					'description' => __( 'A list of connected User Nodes', 'wp-graphql' ),
				],
			],
		] );

		register_graphql_interface_type( 'UserConnectionEdge', [
			'interfaces'  => [ 'Edge' ],
			'description' => __( 'Edge between a Node and a connected User', 'wp-graphql' ),
			'fields'      => [
				'type'        => [ 'non_null' => 'User' ],
				'description' => __( 'The connected User Node', 'wp-graphql' ),
			],
		]);

	}

}
