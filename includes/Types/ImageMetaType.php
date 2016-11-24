<?php
namespace DFM\WPGraphQL\Types;

use Youshido\GraphQL\Type\Object\AbstractObjectType;

class ImageMetaType extends AbstractObjectType {

	/**
	 * @return mixed
	 * @since 0.0.1
	 */
	public function getDescription() {
		return __( 'The Image Meta for the media object', 'wp-graphql' );
	}

	/**
	 * @param \Youshido\GraphQL\Config\Object\ObjectTypeConfig $config
	 * @since 0.0.1
	 */
	public function build( $config ) {

		/**
		 * - aperture
		 * - credit
		 * - camera
		 * - caption
		 * - created_timestamp
		 * - copyright
		 * - focal_length
		 * - iso
		 * - shutter_speed
		 * - title
		 * - orientation
		 * - keywords
		 */
		$fields = [

		];

		/**
		 * Pass the fields through a filter
		 * @since 0.0.1
		 */
		$fields = apply_filters( 'DFM\WPGraphQL\Types\ImageMetaType\Fields', $fields );

		/**
		 * Add the fields
		 */
		$config->addFields( $fields );

	}

}