<?php
namespace DFM\WPGraphQL\Fields;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\Scalar\StringType;

class ModifiedField extends AbstractField {

	public function getName() {
		return 'modified';
	}

	public function getType() {
		return new StringType();
	}

	public function getDescription() {
		return __( 'The date the object was last modified, in the site\'s timezone.', 'wp-graphql' );
	}

	public function resolve( $value, array $args, ResolveInfo $info ) {
		return $value->post_modified;
	}

}