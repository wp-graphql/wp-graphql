<?php
namespace DFM\WPGraphQL\Fields;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\Scalar\StringType;

class ModifiedGmtField extends AbstractField {

	public function getName() {
		return 'modified_gmt';
	}

	public function getType() {
		return new StringType();
	}

	public function getDescription() {
		return __( 'The date the object was last modified, as GMT.', 'wp-graphql' );
	}

	public function resolve( $value, array $args, ResolveInfo $info ) {
		return $value->post_modified_gmt;
	}

}