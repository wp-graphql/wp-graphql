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
				'description' => static function () {
					return __( 'Logical operators for filter conditions. Determines whether multiple filtering criteria should be combined with AND (all must match) or OR (any can match).', 'wp-graphql' );
				},
				'values'      => [
					'AND' => [
						'name'        => 'AND',
						'value'       => 'AND',
						'description' => static function () {
							return __( 'All conditions must match (more restrictive filtering)', 'wp-graphql' );
						},
					],
					'OR'  => [
						'name'        => 'OR',
						'value'       => 'OR',
						'description' => static function () {
							return __( 'Any condition can match (more inclusive filtering)', 'wp-graphql' );
						},
					],
				],
			]
		);
	}
}
