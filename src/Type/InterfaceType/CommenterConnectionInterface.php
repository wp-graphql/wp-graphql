<?php

namespace WPGraphQL\Type\InterfaceType;

use Exception;
use WPGraphQL\Registry\TypeRegistry;

class CommenterConnectionInterface {

	/**
	 * Register the CommenterConnection Interface
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ) {

		register_graphql_interface_type( 'CommenterConnection', [
			'interfaces'  => [ 'Connection' ],
			'description' => __( 'Connection to Commenter Nodes', 'wp-graphql' ),
			'fields'      => [
				'edges' => [
					'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'CommenterConnectionEdge' ] ] ],
					'description' => __( 'A list of edges (relational context) between connected nodes', 'wp-graphql' ),
				],
				'nodes' => [
					'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'Commenter' ] ] ],
					'description' => __( 'A list of connected Commenter Nodes', 'wp-graphql' ),
				],
			],
		] );

		register_graphql_interface_type( 'CommenterConnectionEdge', [
			'interfaces'  => [ 'Edge' ],
			'description' => __( 'Edge between a Node and a connected Commenter', 'wp-graphql' ),
			'fields'      => [
				'type'        => [ 'non_null' => 'Commenter' ],
				'description' => __( 'The connected Commenter Node', 'wp-graphql' ),
			],
		]);

	}

}
