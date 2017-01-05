<?php
namespace DFM\WPGraphQL\Types\PostObject\TaxQuery;

use Youshido\GraphQL\Type\InputObject\AbstractInputObjectType;
use Youshido\GraphQL\Type\ListType\ListType;


class TaxQueryType extends AbstractInputObjectType {

	public function build( $config ) {

		$config->addFields(
			[
				[
					'name' => 'relation',
					'type' => new TaxQueryRelationEnum(),
					'description' => __( 'The logical relationship between each inner taxonomy array when there is more than one. Possible values are \'AND\', \'OR\'. Do not use with a single inner taxonomy array.', 'wp-graphql' )
				],
				[
					'name' => 'args',
					'type' => new ListType( new TaxQueryArray() ),
					'description' => __( 'Array of tax fields to query', 'wp-graphql' )
				]
			]
		);

	}
}