<?php

namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class Connection {
	/**
	 * Register the Connection Interface
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @throws \Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {
		register_graphql_interface_type(
			'Connection',
			[
				'description' => __( 'A paginated relationship between objects. Supports cursor-based pagination with edges containing relationship metadata and nodes containing the related objects.', 'wp-graphql' ),
				'fields'      => [
					'pageInfo' => [
						'type'        => [ 'non_null' => 'PageInfo' ],
						'description' => __( 'Information about pagination in a connection.', 'wp-graphql' ),
					],
					'edges'    => [
						'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'Edge' ] ] ],
						'description' => __( 'A list of edges (relational context) between connected nodes', 'wp-graphql' ),
					],
					'nodes'    => [
						'type'        => [ 'non_null' => [ 'list_of' => [ 'non_null' => 'Node' ] ] ],
						'description' => __( 'A list of connected nodes', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
