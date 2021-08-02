<?php

namespace WPGraphQL\Type\Input;

class TermObjectsConnectionOrderbyInput {
	public static function register_type() {
		register_graphql_input_type(
			'TermObjectsConnectionOrderbyInput',
			[
				'description' => __( 'Options for ordering the connection', 'wp-graphql' ),
				'fields'      => [
					'field' => [
						'type'        => [
							'non_null' => 'TermObjectsConnectionOrderbyEnum',
						],
						'description' => __( 'The field to order the connection by', 'wp-graphql' ),
          ],
					'order' => [
						'type'        => [
							'non_null' => 'OrderEnum',
						],
						'description' => __( 'Possible directions in which to order a list of items', 'wp-graphql' ),
					],
					'metaKeyField' => [
						'type'        => 'String',
						'description' => __( 'Array of names to return term(s) for. Default empty.', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
