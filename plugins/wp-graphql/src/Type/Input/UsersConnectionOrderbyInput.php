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
				'description' => static function () {
					return __( 'Options for ordering the connection', 'wp-graphql' );
				},
				'fields'      => static function () {
					return [
						'field' => [
							'description' => static function () {
								return __( 'The field name used to sort the results.', 'wp-graphql' );
							},
							'type'        => [
								'non_null' => 'UsersConnectionOrderbyEnum',
							],
						],
						'order' => [
							'description' => static function () {
								return __( 'The cardinality of the order of the connection', 'wp-graphql' );
							},
							'type'        => 'OrderEnum',
						],
					];
				},
			]
		);
	}
}
