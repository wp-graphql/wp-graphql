<?php

namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class Edge {
	/**
	 * Register the Connection Interface
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @throws \Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {
		register_graphql_interface_type(
			'Edge',
			[
				'description' => __( 'Represents a connection between two objects. Contains both the related object (node) and metadata about the relationship (cursor).', 'wp-graphql' ),
				'fields'      => [
					'cursor' => [
						'type'        => 'String',
						'description' => __( 'Opaque reference to the nodes position in the connection. Value can be used with pagination args.', 'wp-graphql' ),
					],
					'node'   => [
						'type'        => [ 'non_null' => 'Node' ],
						'description' => __( 'The connected node', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
