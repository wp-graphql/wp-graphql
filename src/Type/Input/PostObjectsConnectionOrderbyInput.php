<?php

namespace WPGraphQL\Type\Input;

class PostObjectsConnectionOrderbyInput {

	/**
	 * Register the PostObjectsConnectionOrderbyInput Input
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_input_type(
			'PostObjectsConnectionOrderbyInput',
			[
				'description' => __( 'Options for ordering the connection', 'wp-graphql' ),
				'fields'      => [
					'field' => [
						'type'        => [
							'non_null' => 'PostObjectsConnectionOrderbyEnum',
						],
						'description' => __( 'The field to order the connection by', 'wp-graphql' ),
					],
					'order' => [
						'type'        => [
							'non_null' => 'OrderEnum',
						],
						'description' => __( 'Possible directions in which to order a list of items', 'wp-graphql' ),
					],
				],
			]
		);

	}
}
