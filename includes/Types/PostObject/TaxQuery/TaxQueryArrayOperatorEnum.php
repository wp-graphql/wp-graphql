<?php
namespace DFM\WPGraphQL\Types\PostObject\TaxQuery;

use Youshido\GraphQL\Type\Enum\AbstractEnumType;

/**
 * Class TaxQueryArrayOperatorEnum
 *
 * Returns the possible values for the TaxQueryArray operator field
 *
 * @package DFM\WPGraphQL\Types\QueryTypes
 * @since 0.0.2
 */
class TaxQueryArrayOperatorEnum extends AbstractEnumType {

	/**
	 * getValues
	 * @return array
	 * @since 0.0.2
	 *
	 * 'IN', 'NOT IN', 'AND', 'EXISTS' and 'NOT EXISTS'
	 */
	public function getValues() {
		return [
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
		];
	}
}