<?php

namespace WPGraphQL\Type\InterfaceType;

use Exception;
use WPGraphQL\Registry\TypeRegistry;

class CommentConnection {

	/**
	 * Register the CommentConnection Interface
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ) {

		register_graphql_interface_type( 'CommentConnection', [
			'interfaces'  => [ 'Connection' ],
			'description' => __( 'Connection to Comment Nodes', 'wp-graphql' ),
			'fields'      => [
				'edges' => [
					'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'CommentConnectionEdge' ] ] ],
					'description' => __( 'A list of edges (relational context) between connected nodes', 'wp-graphql' ),
				],
				'nodes' => [
					'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'Comment' ] ] ],
					'description' => __( 'A list of connected Comment Nodes', 'wp-graphql' ),
				],
			],
		] );

		register_graphql_interface_type( 'CommentConnectionEdge', [
			'interfaces'  => [ 'Edge' ],
			'description' => __( 'Edge between a Node and a connected Comment', 'wp-graphql' ),
			'fields'      => [
				'type'        => [ 'non_null' => 'Comment' ],
				'description' => __( 'The connected Comment Node', 'wp-graphql' ),
			],
		]);

	}

}
