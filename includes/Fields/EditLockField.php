<?php
namespace DFM\WPGraphQL\Fields;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class EditLock
 * @package DFM\WPGraphQL\Fields
 * @since 0.0.2
 */
class EditLock extends AbstractField {

	/**
	 * @return string
	 * @since 0.0.2
	 */
	public function getName() {
		return 'edit_lock';
	}

	/**
	 * @return StringType
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
		return __( 'String indicating the timestamp and ID of the user that most 
		recently edited an object. Can be used to determine if it can safely be 
		edited by another user.', 'wp-graphql' );
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