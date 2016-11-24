<?php
namespace DFM\WPGraphQL\Fields;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\Scalar\StringType;

class TitleField extends AbstractField {

	public function getName() {
		return 'title';
	}

	public function getType() {
		return new StringType();
	}

	public function getDescription() {
		return __( 'The title for the object.', 'wp-graphql' );
	}

	public function resolve( $value, array $args, ResolveInfo $info ) {
		return $value->post_title;
	}

}