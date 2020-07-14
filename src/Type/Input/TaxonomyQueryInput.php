<?php

namespace WPGraphQL\Type\Input;

class TaxonomyQueryInput {
	public static function register_type() {
		register_graphql_input_type(
			'TaxonomyQueryInput',
			[
				'description' => __( 'Taxonomy parameters to query on.', 'wp-graphql' ),
				'fields'      => [
					'relation' => [
						'type' => 'RelationEnum',
					],
					'taxArray' => [
						'type' => [
							'list_of' => 'TaxonomyArrayInput',
						],
					],
				],
			]
		);
	}
}
