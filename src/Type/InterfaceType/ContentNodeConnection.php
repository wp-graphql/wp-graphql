<?php

namespace WPGraphQL\Type\InterfaceType;

use Exception;
use WPGraphQL\Registry\TypeRegistry;

class ContentNodeConnection {

	/**
	 * Register the ContentNodeConnection Interface
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {

		register_graphql_interface_type( 'ContentNodeConnection', [
			'interfaces'  => [ 'Connection' ],
			'description' => __( 'Connection to Content Nodes', 'wp-graphql' ),
			'fields'      => [
				'edges' => [
					'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'ContentNodeConnectionEdge' ] ] ],
					'description' => __( 'A list of edges (relational context) between connected nodes', 'wp-graphql' ),
				],
				'nodes' => [
					'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'ContentNode' ] ] ],
					'description' => __( 'A list of connected Content Nodes', 'wp-graphql' ),
				],
			],
		] );

		register_graphql_interface_type( 'ContentNodeConnectionEdge', [
			'interfaces'  => [ 'Edge' ],
			'description' => __( 'Edge between a Node and a connected Content Node', 'wp-graphql' ),
			'fields'      => [
				'type'        => [ 'non_null' => 'ContentNode' ],
				'description' => __( 'The connected Content Node', 'wp-graphql' ),
			],
		]);

	}

}
