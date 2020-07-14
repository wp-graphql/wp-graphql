<?php
namespace WPGraphQL\Type\Enum;

class TaxonomyQueryOperatorEnum {

	/**
	 * Register the Enum used for setting the field to identify term nodes by
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'TaxonomyQueryOperatorEnum',
			[
				'description' => __( 'Operator to test. Default value is "IN".', 'wp-graphql' ),
				'values'      => [
					'IN'         => [
						'name'  => 'IN',
						'value' => 'IN',
					],
					'NOT_IN'     => [
						'name'  => 'NOT_IN',
						'value' => 'NOT IN',
					],
					'AND'        => [
						'name'  => 'AND',
						'value' => 'AND',
					],
					'EXISTS'     => [
						'name'  => 'EXISTS',
						'value' => 'EXISTS',
					],
					'NOT_EXISTS' => [
						'name'  => 'NOT_EXISTS',
						'value' => 'NOT EXISTS',
					]
				],
			]
		);
	}
}

