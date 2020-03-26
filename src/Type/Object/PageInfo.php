<?php

namespace WPGraphQL\Type\Object;

class PageInfo {
	public static function register_type() {

		/**
		 * Note: This was added as WPPageInfo to avoid conflicts with
		 * the PageInfo type that's registered in the Relay library.
		 *
		 * @todo: Ideally, when the relay library is deprecated this can be changed
		 * back to PageInfo – which would be another breaking change at that time
		 */
		register_graphql_object_type(
			'WPPageInfo',
			[
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

			]
		);

	}
}
