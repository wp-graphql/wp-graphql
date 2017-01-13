<?php
namespace WPGraphQL\Types\PostObject\TaxQuery;

use Youshido\GraphQL\Type\Enum\AbstractEnumType;

/**
 * Class TaxQueryRelationEnum
 *
 * Returns the possible values for the TaxQuery relation field
 *
 * @package WPGraphQL\Types\QueryTypes
 * @since 0.0.2
 */
class TaxQueryRelationEnum extends AbstractEnumType {

	/**
	 * getValues
	 * @return array
	 * @since 0.0.2
	 */
	public function getValues() {
		return [
			[
				'name'  => 'AND',
				'value' => 'AND',
				'description' => __( 'When using multiple tax queries this combines to match both queries', 'wp-graphql' ),
			],
			[
				'name'  => 'OR',
				'value' => 'OR',
				'description' => __( 'When using multiple tax queries this allows for either query to match', 'wp-graphql' ),
			]
		];
	}
}