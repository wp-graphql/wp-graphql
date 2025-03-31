<?php

namespace WPGraphQL\Type\Enum;

class RelationEnum {

	/**
	 * Register the RelationEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'RelationEnum',
			[
				'description' => __( 'Logical operators for filter conditions. Determines whether multiple filtering criteria should be combined with AND (all must match) or OR (any can match).', 'wp-graphql' ),
				'values'      => [
					'AND' => [
						'name'        => 'AND',
						'value'       => 'AND',
						'description' => __( 'The logical AND condition returns true if both operands are true, otherwise, it returns false.', 'wp-graphql' ),
					],
					'OR'  => [
						'name'        => 'OR',
						'value'       => 'OR',
						'description' => __( 'The logical OR condition returns false if both operands are false, otherwise, it returns true.', 'wp-graphql' ),
					],
				],
			]
		);
	}
}
