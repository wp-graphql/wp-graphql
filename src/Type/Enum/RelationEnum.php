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
				'description' => __( 'The logical relation between each item in the array when there are more than one.', 'wp-graphql' ),
				'values'      => [
					'AND' => [
						'name'  => 'AND',
						'value' => 'AND',
					],
					'OR'  => [
						'name'  => 'OR',
						'value' => 'OR',
					],
				],
			]
		);
	}
}
