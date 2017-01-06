<?php
namespace DFM\WPGraphQL\Fields;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class PostExcerptField
 * @package DFM\WPGraphQL\Fields
 * @since 0.0.1
 */
class PostExcerptField extends AbstractField {

	/**
	 * @return string
	 * @since 0.0.1
	 */
	public function getName() {
		return 'post_excerpt';
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
		return __( 'The excerpt for the object.', 'wp-graphql' );
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
		return apply_filters( 'the_excerpt', apply_filters( 'get_the_excerpt', $value->post_excerpt, $value ) );
	}

}