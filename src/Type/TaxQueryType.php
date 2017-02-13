<?php
namespace WPGraphQL\Type;

use GraphQL\Language\AST\Type;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use WPGraphQL\Types;

class TaxQueryType extends InputObjectType {

	public function __construct() {
		$config = [
			'name'        => 'taxQuery',
			'description' => __( 'Query objects based on taxonomy parameters', 'wp-graphql' ),
			'fields'      => function() {
				$fields = [
					'relation' => [
						'type' => Types::relation_enum(),
					],
					'taxArray' => Types::list_of(
						new InputObjectType( [
							'name'   => 'taxArray',
							'fields' => function() {
								$fields = [
									'taxonomy'        => [
										'name' => 'taxEnum',
										'type' => Types::taxonomy_enum(),
									],
									'field'           => [
										'type' => Types::tax_query_field_enum(),
									],
									'terms'           => [
										'type'        => Types::list_of( Types::string() ),
										'description' => __( 'A list of term slugs', 'wp-graphql' ),
									],
									'includeChildren' => [
										'type'        => Types::boolean(),
										'description' => __( 'Whether or not to include children for hierarchical 
										taxonomies. Defaults to true', 'wp-graphql' ),
									],
									'operator'        => [
										'type' => Types::tax_query_operator_enum(),
									],
								];
								return $fields;
							},
						] )
					),
				];
				return $fields;
			},
		];
		parent::__construct( $config );
	}
}
