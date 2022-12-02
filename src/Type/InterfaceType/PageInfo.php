<?php

namespace WPGraphQL\Type\InterfaceType;

use Exception;
use WPGraphQL\Registry\TypeRegistry;

class PageInfo {
	/**
	 * Register the PageInfo Interface
	 *
	 * @param TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws Exception
	 */
	public static function register_type( TypeRegistry $type_registry ): void {

		register_graphql_interface_type( 'WPPageInfo', [
			'description' => __( 'A singular connection from one Node to another, with support for relational data on the "edge" of the connection.', 'wp-graphql' ),
			'interfaces'  => [ 'PageInfo' ],
			'fields'      => [
				'hasNextPage'     => [
					'type'        => [
						'non_null' => 'Boolean',
					],
					'description' => __( 'When paginating forwards, are there more items?', 'wp-graphql' ),
				],
				'hasPreviousPage' => [
					'type'        => [
						'non_null' => 'Boolean',
					],
					'description' => __( 'When paginating backwards, are there more items?', 'wp-graphql' ),
				],
				'startCursor'     => [
					'type'        => 'String',
					'description' => __( 'When paginating backwards, the cursor to continue.', 'wp-graphql' ),
				],
				'endCursor'       => [
					'type'        => 'String',
					'description' => __( 'When paginating forwards, the cursor to continue.', 'wp-graphql' ),
				],
			],
		] );

		register_graphql_interface_type( 'PageInfo', [
			'description' => __( 'A singular connection from one Node to another, with support for relational data on the "edge" of the connection.', 'wp-graphql' ),
			'fields'      => [
				'hasNextPage'     => [
					'type'        => [
						'non_null' => 'Boolean',
					],
					'description' => __( 'When paginating forwards, are there more items?', 'wp-graphql' ),
				],
				'hasPreviousPage' => [
					'type'        => [
						'non_null' => 'Boolean',
					],
					'description' => __( 'When paginating backwards, are there more items?', 'wp-graphql' ),
				],
				'startCursor'     => [
					'type'        => 'String',
					'description' => __( 'When paginating backwards, the cursor to continue.', 'wp-graphql' ),
				],
				'endCursor'       => [
					'type'        => 'String',
					'description' => __( 'When paginating forwards, the cursor to continue.', 'wp-graphql' ),
				],
			],
		] );

	}
}
