<?php
namespace DFM\WPGraphQL\Fields;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\Scalar\IdType;

/**
 * Class ParentIdField
 *
 * The post_parent ID of the object
 *
 * @package DFM\WPGraphQL\Fields
 * @since 0.0.1
 */
class ParentIdField extends AbstractField {

	/**
	 * @return string
	 * @since 0.0.1
	 */
	public function getName() {
		return 'parent_id';
	}

	/**
	 * @return IdType
	 * @since 0.0.1
	 */
	public function getType() {
		return new IdType();
	}

	/**
	 * @return mixed
	 * @since 0.0.1
	 */
	public function getDescription() {
		return __( 'The id for the author of the object.', 'wp-graphql' );
	}

	/**
	 * @param $value
	 * @param array $args
	 * @param ResolveInfo $info
	 *
	 * @return mixed
	 * @since 0.0.1
	 */
	public function resolve( $value, array $args, ResolveInfo $info ) {
		return $value->post_parent;
	}

}