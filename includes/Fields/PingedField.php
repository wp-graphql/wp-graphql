<?php
namespace DFM\WPGraphQL\Fields;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\Scalar\BooleanType;

/**
 * Class PingedField
 * @package DFM\WPGraphQL\Fields
 * @since 0.0.2
 */
class PingedField extends AbstractField {

	/**
	 * @return string
	 * @since 0.0.2
	 */
	public function getName() {
		return 'pinged';
	}

	/**
	 * @return BooleanType
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
		return __( 'Whether or not the object has been pinged', 'wp-graphql' );
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
		return ! empty( $value->pinged ) ? true : false;
	}

}
