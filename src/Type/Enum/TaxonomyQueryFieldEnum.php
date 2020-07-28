<?php
namespace WPGraphQL\Type\Enum;

class TaxonomyQueryFieldEnum {

	/**
	 * Register the Enum used for setting the field to identify term nodes by
	 *
	 * @return void
	 */
	public static function register_type() {
		register_graphql_enum_type(
			'TaxonomyQueryFieldEnum',
			[
				'description' => __( 'Which field to select taxonomy term by. Default value is "term_id"',
				'wp-graphql' ),
				'values'      => [
					'ID'          => [
						'name'  => 'ID',
						'value' => 'term_id',
					],
					'NAME'        => [
						'name'  => 'NAME',
						'value' => 'name',
					],
					'SLUG'        => [
						'name'  => 'SLUG',
						'value' => 'slug',
					],
					'TAXONOMY_ID' => [
						'name'  => 'TAXONOMY_ID',
						'value' => 'term_taxonomy_id',
					],
				],
			]
		);
	}
}

