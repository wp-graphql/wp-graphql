<?php

namespace WPGraphQL\Type;

class PageInfo {
	public static function register_type() {
		register_graphql_object_type( 'PageInfo', [
			'description' => __( 'Information about pagination in a connection.', 'wp-graphql' ),
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