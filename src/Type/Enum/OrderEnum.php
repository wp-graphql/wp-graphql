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
				'description'  => __( 'The cardinality of the connection order', 'wp-graphql' ),
				'values'       => [
					'ASC'  => [
						'value' => 'ASC',
					],
					'DESC' => [
						'value' => 'DESC',
					],
				],
				'defaultValue' => 'DESC',
			]
		);
	}
}

