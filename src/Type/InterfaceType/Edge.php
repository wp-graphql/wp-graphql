<?php

namespace WPGraphQL\Type\InterfaceType;

use Exception;
use WPGraphQL\Registry\TypeRegistry;

class Edge {
	/**
	 * Register the Connection Interface
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {

		register_graphql_interface_type( 'Edge', [
			'description' => __( 'Relational context between connected nodes', 'wp-graphql' ),
			'fields'      => [
				'node' => [
					'type'        => [ 'non_null' => 'Node' ],
					'description' => __( 'The connected node', 'wp-graphql' ),
				],
			],
		] );

	}
}
