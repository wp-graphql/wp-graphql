<?php
namespace WPGraphQL\Type\Enum;

use GraphQL\Type\Definition\EnumType;

class TaxQueryOperatorEnumType extends EnumType {

	public function __construct() {

		$config = [
			'name' => 'TaxQueryOperator',
			'values' => [
				[
					'name'  => 'IN',
					'value' => 'IN',
				],
				[
					'name'  => 'NOT_IN',
					'value' => 'NOT IN',
				],
				[
					'name'  => 'AND',
					'value' => 'AND',
				],
				[
					'name'  => 'EXISTS',
					'value' => 'EXISTS',
				],
				[
					'name'  => 'NOT_EXISTS',
					'value' => 'NOT EXISTS',
				],
			],
		];

		parent::__construct( $config );

	}

}