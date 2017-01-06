<?php
namespace DFM\WPGraphQL\Fields\MediaDetails;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class FileField
 * @package DFM\WPGraphQL\Fields
 * @since 0.0.1
 */
class FileField extends AbstractField {

	/**
	 * @return string
	 * @since 0.0.1
	 */
	public function getName() {
		return 'file';
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
		return __( 'The relative path to the resource file.', 'wp-graphql' );
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
		$full_src = wp_get_attachment_image_src( $value->ID, 'full' );
		return ! empty( $full_src[0] ) ? esc_html( $full_src[0] ) : '';
	}

}