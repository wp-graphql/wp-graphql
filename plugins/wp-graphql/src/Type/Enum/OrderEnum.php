<?php

namespace WPGraphQL\Type\Enum;

class OrderEnum {

	/**
	 * Register the OrderEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'OrderEnum',
			[
				'description'  => static function () {
					return __( 'Sort direction for ordered results. Determines whether items are returned in ascending or descending order.', 'wp-graphql' );
				},
				'values'       => [
					'ASC'  => [
						'value'       => 'ASC',
						'description' => static function () {
							return __( 'Results ordered from lowest to highest values (i.e. A-Z, oldest-newest)', 'wp-graphql' );
						},
					],
					'DESC' => [
						'value'       => 'DESC',
						'description' => static function () {
							return __( 'Results ordered from highest to lowest values (i.e. Z-A, newest-oldest)', 'wp-graphql' );
						},
					],
				],
				'defaultValue' => 'DESC',
			]
		);
	}
}
