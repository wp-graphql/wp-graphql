<?php
namespace DFM\WPGraphQL\Fields;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\Scalar\BooleanType;

/**
 * Class EncloseMeField
 * @package DFM\WPGraphQL\Fields
 * @since 0.0.2
 */
class EncloseMeField extends AbstractField {

	/**
	 * @return string
	 * @since 0.0.2
	 */
	public function getName() {
		return 'enclose_me';
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
		return __( 'Whether or not the post needs processed for enclosure', 'wp-graphql' );
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
		$meta = get_post_meta( $value->ID, '_enclose_me', true );
		return ! empty( $meta ) ? true : false;
	}

}