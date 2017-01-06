<?php
namespace DFM\WPGraphQL\Fields;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class StatusField
 * @package DFM\WPGraphQL\Fields
 * @since 0.0.1
 */
class StatusField extends AbstractField {

	/**
	 * @return string
	 * @since 0.0.1
	 */
	public function getName() {
		return 'status';
	}

	/**
	 * @return StringType
	 * @since 0.0.1
	 */
	public function getType() {

		// @todo: enum using get_post_stati (https://codex.wordpress.org/Function_Reference/get_post_stati)
		return new StringType();
	}

	/**
	 * @return mixed
	 * @since 0.0.1
	 */
	public function getDescription() {
		return __( 'A named status for the object.', 'wp-graphql' );
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
		return esc_html( $value->post_status );
	}

}