<?php
namespace DFM\WPGraphQL\Types;

use DFM\WPGraphQL\Fields\MediaDetails\FileField;
use DFM\WPGraphQL\Fields\MediaDetails\HeightField;
use DFM\WPGraphQL\Fields\MediaDetails\WidthField;
use Youshido\GraphQL\Type\Object\AbstractObjectType;

/**
 * Class MediaDetailsType
 * @package DFM\WPGraphQL\Types
 * @since 0.0.1
 */
class MediaDetailsType extends AbstractObjectType {

	/**
	 * @return mixed
	 * @since 0.0.1
	 */
	public function getName() {
		return __( 'media_details', 'wp-graphql' );
	}

	/**
	 * @return mixed
	 * @since 0.0.1
	 */
	public function getDescription() {
		return __( 'Details about the resource file, specific to its type', 'wp-graphql' );
	}

	/**
	 * @param \Youshido\GraphQL\Config\Object\ObjectTypeConfig $config
	 * @since 0.0.1
	 */
	public function build( $config ) {

		/**
		 * file
		 * height
		 * image_meta: {}
		 * sizes: {}
		 * width
		 */
		$fields = [
			new FileField(),
			new HeightField(),
			new WidthField()
		];

		/**
		 * addFields
		 * @since 0.0.1
		 */
		$config->addFields( $fields );

	}

}