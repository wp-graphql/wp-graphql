<?php

namespace WPGraphQL\Type\InterfaceType;

use Exception;
use WPGraphQL\Registry\TypeRegistry;

class ConnectionInterface {
	/**
	 * Register the Connection Interface
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ) {

		register_graphql_interface_type( 'Edge', [
			'description' => __( 'Relational context between connected nodes', 'wp-graphql' ),
			'fields'      => [
				'node' => [
					'type'        => [ 'non_null' => 'Node' ],
					'description' => __( 'The connected node', 'wp-graphql' ),
				],
			],
		] );

		register_graphql_interface_type(
			'Connection',
			[
				'description' => __( 'A plural connection from one Node Type in the Graph to another Node Type, with support for relational data via "edges".', 'wp-graphql' ),
				'fields'      => [
					'edges' => [
						'type'        => [ 'list_of' => [ 'non_null' => 'Edge' ] ],
						'description' => __( 'A list of edges (relational context) between connected nodes', 'wp-graphql' ),
					],
					'nodes' => [
						'type'        => [ 'list_of' => [ 'non_null' => 'Node' ] ],
						'description' => __( 'A list of connected nodes', 'wp-graphql' ),
					],
				],
			]
		);

		register_graphql_interface_type( 'SingleNodeConnectionEdge', [
			'description' => __( 'A singular connection from one Node to another, with support for relational data on the "edge" of the connection.', 'wp-graphql' ),
			'fields'      => [
				'node' => [
					'type'        => [ 'non_null' => 'Node' ],
					'description' => __( 'The connected node', 'wp-graphql' ),
				],
			]
		] );

	}
}
