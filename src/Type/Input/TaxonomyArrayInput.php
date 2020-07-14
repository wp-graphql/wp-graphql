<?php

namespace WPGraphQL\Type\Input;

class TaxonomyArrayInput {
	public static function register_type() {
		register_graphql_input_type(
			'TaxonomyArrayInput',
			[
				'description' => __( 'Query objects based on taxonomy parameters', 'wp-graphql' ),
				'fields'      => [
					'taxonomy'        => [
						'type' => 'TaxonomyEnum',
					],
					'field'           => [
						'type' => 'TaxonomyQueryFieldEnum',
					],
					'terms'           => [
						'type'        => [ 'list_of' => 'String' ],
						'description' => __( 'A list of term slugs', 'wp-graphql' ),
					],
					'includeChildren' => [
						'type'        => 'Boolean',
						'description' => __( 'Whether or not to include children for hierarchical taxonomies. Defaults to false to improve performance (note that this is opposite of the default for WP_Query).', 'wp-graphql' ),
					],
					'operator'        => [
						'type' => 'TaxonomyQueryOperatorEnum',
					],
				],
			]
		);
	}
}
