<?php
namespace DFM\WPGraphQL\Fields;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class TrashStatusField
 * @package DFM\WPGraphQL\Fields
 * @since 0.0.2
 */
class TrashStatusField extends AbstractField {

	/**
	 * @return string
	 * @since 0.0.2
	 */
	public function getName() {
		return 'trashed_status';
	}

	/**
	 * @return IntType
	 * @since 0.0.2
	 */
	public function getType() {
		return new StringType();
	}

	/**
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getDescription() {
		return __( 'The status of the post when it was marked for trash', 'wp-graphql' );
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
		return esc_html( get_post_meta( $value->ID, '_wp_trash_meta_status', true ) );
	}

}
