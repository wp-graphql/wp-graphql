<?php

namespace WPGraphQL\Type\InterfaceType;

use WPGraphQL\Registry\TypeRegistry;

class OneToOneConnection {
	/**
	 * Register the Connection Interface
	 *
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @throws \Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {
		register_graphql_interface_type(
			'OneToOneConnection',
			[
				'description' => __( 'A direct one-to-one relationship between objects. Unlike plural connections, this represents a single related object rather than a collection.', 'wp-graphql' ),
				'interfaces'  => [ 'Edge' ],
				'fields'      => [
					'node' => [
						'type'        => [ 'non_null' => 'Node' ],
						'description' => __( 'The connected node', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
