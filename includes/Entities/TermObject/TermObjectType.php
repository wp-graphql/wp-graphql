<?php
namespace DFM\WPGraphQL\Entities\TermObject;

use DFM\WPGraphQL\Entities\TermObject\Fields\TermGroupIdField;
use DFM\WPGraphQL\Entities\TermObject\Fields\TermTaxonomyField;
use DFM\WPGraphQL\Entities\TermObject\Fields\TermTaxonomyIdField;
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