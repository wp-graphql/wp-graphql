<?php

namespace WPGraphQL\Type\InterfaceType;

use Exception;
use WPGraphQL\Registry\TypeRegistry;

class ContentTypeConnection {

	/**
	 * Register the ContentTypeConnection Interface
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {

		register_graphql_interface_type( 'ContentTypeConnection', [
			'interfaces'  => [ 'Connection' ],
			'description' => __( 'Connection to Content Type Nodes', 'wp-graphql' ),
			'fields'      => [
				'edges' => [
					'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'ContentTypeConnectionEdge' ] ] ],
					'description' => __( 'A list of edges (relational context) between connected nodes', 'wp-graphql' ),
				],
				'nodes' => [
					'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'ContentType' ] ] ],
					'description' => __( 'A list of connected Content Type Nodes', 'wp-graphql' ),
				],
			],
		] );

		register_graphql_interface_type( 'ContentTypeConnectionEdge', [
			'interfaces'  => [ 'Edge' ],
			'description' => __( 'Edge between a Node and a connected Content Type Node', 'wp-graphql' ),
			'fields'      => [
				'type'        => [ 'non_null' => 'ContentType' ],
				'description' => __( 'The connected Content Type Node', 'wp-graphql' ),
			],
		]);

	}

}
