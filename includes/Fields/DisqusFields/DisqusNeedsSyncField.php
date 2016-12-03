<?php
namespace DFM\WPGraphQL\Fields;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\Scalar\BooleanType;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class DisqusNeedsSync
 * @package DFM\WPGraphQL\Fields
 * @since 0.0.2
 */
class DisqusNeedsSync extends AbstractField {

	/**
	 * @return string
	 * @since 0.0.2
	 */
	public function getName() {
		return 'disqus_needs_sync';
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
		return __( 'Whether Disqus still needs to sync the object or not', 'wp-graphql' );
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
		return get_post_meta( $value->ID, 'dsq_needs_sync', true );
	}

}
