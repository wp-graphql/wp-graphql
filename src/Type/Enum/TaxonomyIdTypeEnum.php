<?php

namespace WPGraphQL\Type\Enum;

class TaxonomyIdTypeEnum {

	/**
	 * Register the TaxonomyIdTypeEnum Type to the Schema
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'TaxonomyIdTypeEnum',
			[
				'description' => static function () {
					return __( 'Identifier types for retrieving a taxonomy definition. Determines whether to look up taxonomies by ID or name.', 'wp-graphql' );
				},
				'values'      => [
					'ID'   => [
						'name'        => 'ID',
						'value'       => 'id',
						'description' => static function () {
							return __( 'The globally unique ID', 'wp-graphql' );
						},
					],
					'NAME' => [
						'name'        => 'NAME',
						'value'       => 'name',
						'description' => static function () {
							return __( 'The name of the taxonomy', 'wp-graphql' );
						},
					],
				],
			]
		);
	}
}
