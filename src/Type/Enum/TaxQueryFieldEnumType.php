<?php
namespace WPGraphQL\Type\Enum;

use GraphQL\Type\Definition\EnumType;

class TaxQueryFieldEnumType extends EnumType {

	public function __construct() {

		$config = [
			'name' => 'TaxQueryField',
			'description' => __( 'elect taxonomy term by. Default value is "term_id"', 'wp-graphql' ),
			'values' => [
				[
					'name'  => 'ID',
					'value' => 'term_id',
				],
				[
					'name'  => 'NAME',
					'value' => 'name',
				],
				[
					'name'  => 'SLUG',
					'value' => 'slug',
				],
				[
					'name'  => 'TAXONOMY_ID',
					'value' => 'term_taxonomy_id',
				],
			],
		];

		parent::__construct( $config );

	}

}