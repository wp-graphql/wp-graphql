<?php
namespace DFM\WPGraphQL\Types;

use DFM\WPGraphQL\Entities\TermObject\TermGroupId;
use DFM\WPGraphQL\Entities\TermObject\TermTaxonomy;
use DFM\WPGraphQL\Entities\TermObject\TermTaxonomyId;
use DFM\WPGraphQL\Fields\CountField;
use DFM\WPGraphQL\Fields\IdField;
use DFM\WPGraphQL\Fields\NameField;
use DFM\WPGraphQL\Fields\ParentIdField;
use DFM\WPGraphQL\Fields\SlugField;
use Youshido\GraphQL\Type\Object\AbstractObjectType;

class TermObjectType extends AbstractObjectType {
	
	public function getName() {
		return $this->getConfig()->get( 'taxonomy_name' );
	}

	public function getDescription() {
		return __( 'The base WordPress Term Type', 'wp-graphql' );
	}

	public function build( $config ) {
		
		$fields = [
			new IdField(),
			new NameField(),
			new SlugField(),
			new TermGroupId(),
			new TermTaxonomyId(),
			new TermTaxonomy(),
			new ParentIdField(),
			new CountField(),
		];

		$fields = apply_filters( 'wpgraphql_term_object_type_fields_' . $config->get( 'taxonomy' ) , $fields, $config );

		$config->addFields( $fields );

	}

}