<?php

namespace WPGraphQL\Type\InterfaceType;

use Exception;
use WPGraphQL\Registry\TypeRegistry;

class Connection {
	/**
	 * Register the Connection Interface
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {

		register_graphql_interface_type(
			'Connection',
			[
				'description' => __( 'A plural connection from one Node Type in the Graph to another Node Type, with support for relational data via "edges".', 'wp-graphql' ),
				'fields'      => [
					'edges' => [
						'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'Edge' ] ] ],
						'description' => __( 'A list of edges (relational context) between connected nodes', 'wp-graphql' ),
					],
					'nodes' => [
						'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'Node' ] ] ],
						'description' => __( 'A list of connected nodes', 'wp-graphql' ),
					],
				],
			]
		);

	}
}
