<?php
namespace DFM\WPGraphQL\Fields;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class PostContentField
 * @package DFM\WPGraphQL\Fields
 * @since 0.0.1
 */
class PostContentField extends AbstractField {

	/**
	 * @return string
	 * @since 0.0.1
	 */
	public function getName() {
		return 'post_content';
	}

	/**
	 * @return StringType
	 * @since 0.0.1
	 */
	public function getType() {
		return new StringType();
	}

	/**
	 * @return mixed
	 * @since 0.0.1
	 */
	public function getDescription() {
		return __( 'The content for the object.', 'wp-graphql' );
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
		return apply_filters( 'the_content', $value->post_content );
	}

}