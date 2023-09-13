<?php

namespace WPGraphQL\Type\Input;

class UsersConnectionOrderbyInput {

	/**
	 * Register the UsersConnectionOrderbyInput Input
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_input_type(
			'UsersConnectionOrderbyInput',
			[
				'description' => __( 'Options for ordering the connection', 'wp-graphql' ),
				'fields'      => [
					'field' => [
						'description' => __( 'The field name used to sort the results.', 'wp-graphql' ),
						'type'        => [
							'non_null' => 'UsersConnectionOrderbyEnum',
						],
					],
					'order' => [
						'description' => __( 'The cardinality of the order of the connection', 'wp-graphql' ),
						'type'        => 'OrderEnum',
					],
				],
			]
		);
	}
}
