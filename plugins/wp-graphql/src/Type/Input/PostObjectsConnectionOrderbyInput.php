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
				'description' => static function () {
					return __( 'Options for ordering the connection', 'wp-graphql' );
				},
				'fields'      => static function () {
					return [
						'field' => [
							'type'        => [
								'non_null' => 'PostObjectsConnectionOrderbyEnum',
							],
							'description' => static function () {
								return __( 'The field to order the connection by', 'wp-graphql' );
							},
						],
						'order' => [
							'type'        => [
								'non_null' => 'OrderEnum',
							],
							'description' => static function () {
								return __( 'Possible directions in which to order a list of items', 'wp-graphql' );
							},
						],
					];
				},
			]
		);
	}
}
