<?php
namespace DFM\WPGraphQL\Fields;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\Scalar\IntType;

/**
 * Class ThumbnailIdField
 * @package DFM\WPGraphQL\Fields
 * @since 0.0.2
 */
class ThumbnailIdField extends AbstractField {

	/**
	 * @return string
	 * @since 0.0.2
	 */
	public function getName() {
		return 'thumbnail_id';
	}

	/**
	 * @return IntType
	 * @since 0.0.2
	 */
	public function getType() {
		return new IntType();
	}

	/**
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getDescription() {
		return __( 'The ID of the featured image thumbnail for the object', 'wp-graphql' );
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
		$meta = get_post_meta( $value->ID, '_thumbnail_id', true );
		return ! empty( $meta ) ? absint( $meta ) : null;
	}

}
