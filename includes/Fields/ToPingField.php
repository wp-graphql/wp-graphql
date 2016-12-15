<?php
namespace DFM\WPGraphQL\Fields;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\Scalar\BooleanType;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class ToPingField
 *
 * The "to_ping" flag of the object
 *
 * @package DFM\WPGraphQL\Fields
 * @since 0.0.2
 */
class ToPingField extends AbstractField {

	/**
	 * @return string
	 * @since 0.0.2
	 */
	public function getName() {
		return 'to_ping';
	}

	/**
	 * @return IdType
	 * @since 0.0.2
	 */
	public function getType() {
		return new BooleanType();
	}

	/**
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getDescription() {
		return __( 'The "to_ping" flag of the object.', 'wp-graphql' );
	}

	/**
	 * @param $value
	 * @param array $args
	 * @param ResolveInfo $info
	 *
	 * @return mixed
	 * @since 0.0.2
	 */
	public function resolve( $value, array $args, ResolveInfo $info ) {
		return ( $value->to_ping ) ? true : false;
	}

}