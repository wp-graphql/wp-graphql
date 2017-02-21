<?php
namespace WPGraphQL\Type\Enum;

use GraphQL\Type\Definition\EnumType;

class RelationEnum extends EnumType {

	public function __construct() {

		$config = [
			'name' => 'relation',
			'relation' => __( 'The logical relation between each item in the array when there are more than 
			one.', 'wp-graphql' ),
			'values' => [
				[
					'name' => 'AND',
					'value' => 'AND',
				],
				[
					'name' => 'OR',
					'value' => 'OR',
				],
			],
		];

		parent::__construct( $config );

	}

}