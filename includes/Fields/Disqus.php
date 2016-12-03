<?php
namespace DFM\WPGraphQL\Fields;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class _MetaTitle
 * @package DFM\WPGraphQL\Fields
 * @since 0.0.2
 */
class _OldSlug extends AbstractField {

	/**
	 * @return string
	 * @since 0.0.2
	 */
	public function getName() {
		return '_wp_old_slug';
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
		return __( 'The old slug of the object. Can be used to find object where the slug changed or can be used to redirect old slugs to new slugs', 'wp-graphql' );
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
		return absint( get_post_meta( $value->ID, '_wp_old_slug', true ) );
	}

}
