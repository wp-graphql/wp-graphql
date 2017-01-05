<?php
namespace DFM\WPGraphQL\Types\TermObject;

use DFM\WPGraphQL\Types\TermObject\Fields\TermGroupIdField;
use DFM\WPGraphQL\Types\TermObject\Fields\TermTaxonomyField;
use DFM\WPGraphQL\Types\TermObject\Fields\TermTaxonomyIdField;
use DFM\WPGraphQL\Fields\CountField;
use DFM\WPGraphQL\Fields\NameField;
use DFM\WPGraphQL\Fields\ParentIdField;
use DFM\WPGraphQL\Fields\SlugField;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;

class TermObjectType extends AbstractObjectType {
	
	public function getName() {
		return $this->getConfig()->get( 'taxonomy_name' );
	}

	public function getDescription() {
		return __( 'The base WordPress Term Type', 'wp-graphql' );
	}

	public function build( $config ) {
		
		$fields = [
			// note, since terms resolve with term_id instead of `id` we have to define
			// a different field than what we use for the post objects
			'id' => [
				'name' => 'id',
				'type' => new IntType(),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return ! empty( $value->term_id ) ? absint( $value->term_id ) : null;
				},
			],
			new NameField(),
			new SlugField(),
			new TermGroupIdField(),
			new TermTaxonomyIdField(),
			new TermTaxonomyField(),
			new ParentIdField(),
			new CountField(),
		];

		$fields = apply_filters( 'wpgraphql_term_object_type_fields_' . $config->get( 'taxonomy' ), $fields, $config );

		$config->addFields( $fields );

	}

}