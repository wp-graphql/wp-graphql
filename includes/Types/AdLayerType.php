<?php
namespace DFM\WPGraphQL\Types;

use DFM\WPGraphQL\Types\Interfaces\PostObjectInterface;
use DFM\WPGraphQL\Types\MediaDetailsType;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class AdLayerType
 *
 * @package DFM\WPGraphQL\Types
 * @since 0.0.1
 */
class AdLayerType extends AbstractObjectType  {

	/**
	 * getDescription
	 *
	 * This returns the description of the Attachment Type
	 *
	 * @return mixed
	 * @since 0.0.1
	 */
	public function getDescription() {
		return __( 'Ad Layer post type', 'wp-graphql' );
	}

	/**
	 * build
	 *
	 * This defines the fields for the AttachmentType. It uses the PostTypeInterface to include
	 * the standard PostObject fields
	 *
	 * @param \Youshido\GraphQL\Config\Object\ObjectTypeConfig $config
	 * @since 0.0.1
	 */
	public function build( $config ){

		/**
		 * Applies the PostObjectInterface which defines the core PostObject fields
		 *
		 * @since 0.0.1
		 */
		$config->applyInterface( new PostObjectInterface() );

		// @todo: Add fields for Ad Layers

	}

}