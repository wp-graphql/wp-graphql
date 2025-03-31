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
				'description'  => __( 'Sort direction for ordered results. Determines whether items are returned in ascending or descending order.', 'wp-graphql' ),
				'values'       => [
					'ASC'  => [
						'value'       => 'ASC',
						'description' => __( 'Sort the query result set in an ascending order', 'wp-graphql' ),
					],
					'DESC' => [
						'value'       => 'DESC',
						'description' => __( 'Sort the query result set in a descending order', 'wp-graphql' ),
					],
				],
				'defaultValue' => 'DESC',
			]
		);
	}
}
