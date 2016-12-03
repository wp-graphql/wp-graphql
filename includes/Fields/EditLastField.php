<?php
namespace DFM\WPGraphQL\Fields;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\Scalar\IntType;

/**
 * Class EditLast
 * @package DFM\WPGraphQL\Fields
 * @since 0.0.2
 */
class EditLast extends AbstractField {

	/**
	 * @return string
	 * @since 0.0.2
	 */
	public function getName() {
		return 'edit_last';
	}

	/**
	 * @return StringType
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
		return __( 'The ID of the user that most recently edited the object', 'wp-graphql' );
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
		return absint( get_post_meta( $value->ID, '_edit_lock', true ) );
	}

}