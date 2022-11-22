<?php

namespace WPGraphQL\Type\InterfaceType;

use Exception;
use WPGraphQL\Registry\TypeRegistry;

class ConnectionEdge {
	/**
	 * Register the Connection Interface
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {

		register_graphql_interface_type( 'ConnectionEdge', [
			'description' => __( 'Relational context between connected nodes', 'wp-graphql' ),
			'interfaces' => [ 'Edge' ],
			'fields'      => [
				'cursor' => [
					'type'              => 'String',
					'description'       => __( 'A cursor for use in pagination', 'wp-graphql' ),
				],
				'node' => [
					'type'        => [ 'non_null' => 'Node' ],
					'description' => __( 'The connected node', 'wp-graphql' ),
				],
			],
		] );

	}
}
