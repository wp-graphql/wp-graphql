<?php
namespace DFM\WPGraphQL\Types\PostObject\TaxQuery;

use Youshido\GraphQL\Type\InputObject\AbstractInputObjectType;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Scalar\BooleanType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

class TaxQueryArray extends AbstractInputObjectType {

	public function build( $config ) {

		$config->addFields(
			[
				[
					'name' => 'taxonomy',
					'type' => new NonNullType( new StringType() ),
					'description' => __( 'Name of the taxonomy to query', 'wp-graphql' )
				],
				[
					'name' => 'field',
					'type' => new StringType(),
					'description' => __( 'The field to select the taxonomy term by. Possible values are \'term_id\', \'name\', \'slug\' or \'term_taxonomy_id\'. Default value is \'term_id\'.', 'wp-graphql' )
				],
				[
					'name' => 'terms',
					'type' => new ListType( new IntType() ),
					'description' => __( 'Taxonomy term IDs', 'wp-graphql' )
				],
				[
					'name' => 'include_children',
					'type' => new BooleanType(),
					'description' => __( 'Whether or not to include children for hierarchical taxonomies. Defaults to true', 'wp-graphql' )
				],
				[
					'name' => 'operator',
					'type' => new TaxQueryArrayOperatorEnum(),
					'description' => __( 'Operator to test. Possible values are \'IN\', \'NOT IN\', \'AND\', \'EXISTS\' and \'NOT EXISTS\'. Default value is \'IN\'', 'wp-graphql' )
				]
			]
		);

	}
}