<?php
namespace DFM\WPGraphQL\Fields\SEOFields;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class MetaTitleField
 * @package DFM\WPGraphQL\Fields
 * @since 0.0.2
 */
class MetaTitleField extends AbstractField {

	/**
	 * @return string
	 * @since 0.0.2
	 */
	public function getName() {
		return 'meta_title';
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
		return __( 'The meta title for the object, used for SEO', 'wp-graphql' );
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
		return esc_html( get_post_meta( $value->ID, '_meta_title', true ) );
	}

}
