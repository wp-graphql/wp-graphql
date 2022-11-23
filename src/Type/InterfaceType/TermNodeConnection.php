<?php

namespace WPGraphQL\Type\InterfaceType;

use Exception;
use WPGraphQL\Registry\TypeRegistry;

class TermNodeConnection {

	/**
	 * Register the TermNodeConnection Interface
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {

		register_graphql_interface_type( 'TermNodeConnection', [
			'interfaces'  => [ 'Connection' ],
			'description' => __( 'Connection to Term Nodes', 'wp-graphql' ),
			'fields'      => [
				'edges' => [
					'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'TermNodeConnectionEdge' ] ] ],
					'description' => __( 'A list of edges (relational context) between connected nodes', 'wp-graphql' ),
				],
				'nodes' => [
					'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'TermNode' ] ] ],
					'description' => __( 'A list of connected Term Nodes', 'wp-graphql' ),
				],
			],
		] );

		register_graphql_interface_type( 'TermNodeConnectionEdge', [
			'interfaces'  => [ 'Edge' ],
			'description' => __( 'Edge between a Node and a connected Term Node', 'wp-graphql' ),
			'fields'      => [
				'type'        => [ 'non_null' => 'TermNode' ],
				'description' => __( 'The connected Term Node', 'wp-graphql' ),
			],
		]);

	}

}
